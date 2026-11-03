<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Support\System\Updater;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Requests to the GitHub API for the update sources (the app and the themes), with the right key for each repository.
 *
 * For every repository it remembers (updates/repositories.json) whether it is public or private, which key was used
 * and what went wrong, so the admin can say "public", "private, key ok", "needs a key", "the key has no access".
 * Public repositories never get a key; a key refused by GitHub (expired, revoked) is not a problem for them either:
 * the request is repeated without it.
 */
class GitHub
{
    /** Problems of a repository, translated with admin.updates.github.{code} */
    public const array PROBLEMS = ['needs_key', 'key_denied', 'key_invalid', 'rate_limited', 'unreachable', 'http'];

    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly GitHubKeys $keys,
        private readonly Updater $updater,
    ) {}

    public function file(): string
    {
        return $this->storage->directory().'/repositories.json';
    }

    /**
     * Request with the common headers and no key.
     */
    public function request(int $timeout = 8): PendingRequest
    {
        return Http::timeout($timeout)
            ->connectTimeout(4)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'Resume-App/'.$this->updater->codeVersion(),
            ]);
    }

    public function apiUrl(string $repository, string $path = ''): string
    {
        return rtrim((string) config('resume.updates.api'), '/').'/repos/'.trim($repository, '/').($path !== '' ? '/'.$path : '');
    }

    /**
     * GET of an API path of a repository ("releases/latest", "releases?per_page=100").
     *
     * @param  array<string, string>  $headers
     *
     * @throws ConnectionException
     */
    public function get(string $repository, string $path, array $headers = [], int $timeout = 8): Response
    {
        $url = $this->apiUrl($repository, $path);
        $known = $this->repository($repository);
        $key = $known['private'] === false ? null : $this->keys->forRepository($repository);

        try {
            $response = $this->send($url, $headers, $key, $timeout);

            // Key refused (expired or revoked): a public repository can still be read without it
            if ($key !== null && $response->status() === 401 && $known['private'] !== true) {
                $retry = $this->send($url, $headers, null, $timeout);

                if ($retry->successful() || $retry->status() === 304) {
                    $this->learn($repository, null, $retry);

                    return $retry;
                }
            }

            // Known as public but not found any more: it may have become private, try with its key
            if ($key === null && $known['private'] === false && $response->status() === 404
                && ($key = $this->keys->forRepository($repository)) !== null) {
                $this->remember($repository, ['private' => null]);
                $response = $this->send($url, $headers, $key, $timeout);
            }
        } catch (ConnectionException $exception) {
            $this->remember($repository, ['problem' => 'unreachable', 'status' => null]);

            throw $exception;
        }

        $this->learn($repository, $key, $response);

        return $response;
    }

    /**
     * A file attached to a release: through the API with the key when the repository needs one (the API redirects to a
     * temporary address and the key is not sent there), otherwise through the public link.
     *
     * @param  array{id: int, name: string, size: int, api_url: string, download_url: string}  $asset
     * @param  array<string, string>  $headers
     *
     * @throws ConnectionException
     */
    public function asset(string $repository, array $asset, array $headers = [], int $timeout = 60): Response
    {
        $key = $this->repository($repository)['private'] === false ? null : $this->keys->forRepository($repository);
        $useApi = $key !== null && $asset['api_url'] !== '';

        return $this->send($useApi ? $asset['api_url'] : $asset['download_url'], [...$headers, 'Accept' => 'application/octet-stream'], $useApi ? $key : null, $timeout);
    }

    /**
     * What is known about a repository after the last request.
     *
     * @return array{private: ?bool, problem: ?string, status: ?int, key: ?string, checked_at: ?Carbon}
     */
    public function repository(string $repository): array
    {
        $data = ($this->storage->readJson($this->file()) ?? [])[strtolower($repository)] ?? [];
        $data = is_array($data) ? $data : [];

        return [
            'private' => is_bool($data['private'] ?? null) ? $data['private'] : null,
            'problem' => is_string($data['problem'] ?? null) ? $data['problem'] : null,
            'status' => is_int($data['status'] ?? null) ? $data['status'] : null,
            'key' => is_string($data['key'] ?? null) ? $data['key'] : null,
            'checked_at' => is_string($data['checked_at'] ?? null) ? Carbon::parse($data['checked_at']) : null,
        ];
    }

    /**
     * Checks a key: is it valid, until when, which of the given repositories can it read.
     *
     * @param  list<string>  $repositories
     * @return array{valid: bool, login: ?string, expires_at: ?Carbon, repositories: array<string, bool>, error: ?string}
     */
    public function verify(GitHubKey $key, array $repositories): array
    {
        $result = ['valid' => false, 'login' => null, 'expires_at' => null, 'repositories' => [], 'error' => null];

        try {
            $user = $this->send(rtrim((string) config('resume.updates.api'), '/').'/user', [], $key, 8);
            $result['valid'] = $user->successful();
            $result['login'] = $user->successful() && is_string($user->json('login')) ? $user->json('login') : null;
            $result['expires_at'] = $this->expiration($user);

            if (! $user->successful()) {
                $result['error'] = $this->message($user->status() === 401 ? 'key_invalid' : 'http', $user->status());

                return $result;
            }

            foreach ($repositories as $repository) {
                if (! $key->covers($repository)) {
                    continue;
                }

                $response = $this->send($this->apiUrl($repository), [], $key, 8);
                $result['repositories'][$repository] = $response->successful();

                if ($response->successful()) {
                    $this->remember($repository, ['private' => (bool) $response->json('private'), 'key' => $key->id, 'problem' => null, 'status' => 200]);
                } elseif (in_array($response->status(), [403, 404], true)) {
                    $this->remember($repository, ['key' => $key->id, 'problem' => 'key_denied', 'status' => $response->status()]);
                }
            }
        } catch (ConnectionException) {
            $result['error'] = $this->message('unreachable');
        } catch (Throwable $exception) {
            $result['error'] = $exception->getMessage();
        }

        return $result;
    }

    /**
     * Problem code of a failed response.
     */
    public function problem(Response $response, ?GitHubKey $key): string
    {
        return match (true) {
            $response->status() === 401 => 'key_invalid',
            $response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0' => 'rate_limited',
            in_array($response->status(), [403, 404], true) => $key !== null ? 'key_denied' : 'needs_key',
            default => 'http',
        };
    }

    /**
     * Message for the admin about a problem code.
     */
    public function message(string $problem, ?int $status = null): string
    {
        return in_array($problem, self::PROBLEMS, true)
            ? (string) __('admin.updates.github.'.$problem, ['status' => (string) $status])
            : $problem;
    }

    /**
     * Message for a failed response (used where a download or check fails).
     */
    public function describe(Response $response, ?string $repository = null): string
    {
        $key = $repository !== null && $this->repository($repository)['private'] !== false ? $this->keys->forRepository($repository) : null;

        return $this->message($this->problem($response, $key), $response->status());
    }

    /**
     * @param  array<string, string>  $headers
     */
    private function send(string $url, array $headers, ?GitHubKey $key, int $timeout): Response
    {
        $request = $this->request($timeout)->withHeaders($headers);
        $response = ($key !== null ? $request->withToken($key->token) : $request)->get($url);

        if ($key !== null) {
            $this->observe($key, $response);
        }

        return $response;
    }

    /**
     * Expiration date and validity of a key, from every response that used it.
     */
    private function observe(GitHubKey $key, Response $response): void
    {
        if ($response->status() === 401) {
            $this->keys->remember($key->id, ['status' => 'invalid']);
        } elseif ($response->successful() || $response->status() === 304) {
            // Not every answer carries the expiration date: keep the known one when it is missing
            $expires = $this->expiration($response);
            $this->keys->remember($key->id, $expires !== null ? ['status' => 'ok', 'expires_at' => $expires->toAtomString()] : ['status' => 'ok']);
        }
    }

    /**
     * Header of the tokens with an expiration date: "2027-02-01 10:00:00 +0100" (also "UTC").
     */
    private function expiration(Response $response): ?Carbon
    {
        $header = $response->header('github-authentication-token-expiration');

        try {
            return $header !== '' ? Carbon::parse($header) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Public or private, key and problem of a repository, from a response.
     */
    private function learn(string $repository, ?GitHubKey $key, Response $response): void
    {
        $ok = $response->successful() || $response->status() === 304;

        if ($ok && $key === null) {
            $this->remember($repository, ['private' => false, 'key' => null, 'problem' => null, 'status' => $response->status()]);

            return;
        }

        if ($ok) {
            $private = $this->repository($repository)['private'];

            if ($private === null) {
                // Read with a key: is the key needed? One extra request, only the first time
                try {
                    $info = $this->send($this->apiUrl($repository), [], $key, 8);
                    $private = $info->successful() ? (bool) $info->json('private') : null;
                } catch (Throwable) {
                    $private = null;
                }
            }

            $this->remember($repository, ['private' => $private, 'key' => $key->id, 'problem' => null, 'status' => $response->status()]);

            return;
        }

        $this->remember($repository, ['key' => $key?->id, 'problem' => $this->problem($response, $key), 'status' => $response->status()]);
    }

    /**
     * @param  array<string, mixed>  $facts
     */
    private function remember(string $repository, array $facts): void
    {
        $data = $this->storage->readJson($this->file()) ?? [];
        $name = strtolower($repository);
        $data[$name] = [...(is_array($data[$name] ?? null) ? $data[$name] : []), ...$facts, 'checked_at' => now()->toAtomString()];
        $this->storage->writeJson($this->file(), $data);
    }
}
