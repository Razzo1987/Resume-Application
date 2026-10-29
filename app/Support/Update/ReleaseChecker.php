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
 * New versions published on GitHub. With the hosting cron the check runs at every "updates" job (every 30 minutes);
 * without it at most every 12 hours (when the dashboard is opened, after the response is sent); always with
 * "Check now". The result is kept in core/storage, so the dashboard does not wait for GitHub. Requests are
 * conditional (ETag): when nothing changed GitHub answers 304, which does not count against its rate limit.
 */
class ReleaseChecker
{
    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly Updater $updater,
    ) {}

    /**
     * Result of the last check, without calling GitHub.
     *
     * @return array{checked_at: ?Carbon, release: ?Release, error: ?string}
     */
    public function status(): array
    {
        $data = $this->storage->readJson($this->storage->checkFile()) ?? [];

        return [
            'checked_at' => is_string($data['checked_at'] ?? null) ? Carbon::parse($data['checked_at']) : null,
            'release' => is_array($data['release'] ?? null) ? Release::fromArray($data['release']) : null,
            'error' => is_string($data['error'] ?? null) ? $data['error'] : null,
        ];
    }

    /**
     * ETag of the last answer from GitHub (for the conditional request).
     */
    private function etag(): ?string
    {
        $etag = ($this->storage->readJson($this->storage->checkFile()) ?? [])['etag'] ?? null;

        return is_string($etag) && $etag !== '' ? $etag : null;
    }

    /**
     * The latest release, if it is newer than the installed version (based on the last check).
     */
    public function available(): ?Release
    {
        $release = $this->status()['release'];

        return $release !== null && $this->isNewer($release->version) ? $release : null;
    }

    public function isNewer(string $version): bool
    {
        $current = $this->updater->codeVersion();

        // Development copy (VERSION "dev" or "2.1.0-dev"): no notice
        return preg_match(Release::VERSION_PATTERN, $current) === 1 && version_compare($version, $current, '>');
    }

    public function isStale(): bool
    {
        $checked = $this->status()['checked_at'];

        return $checked === null || $checked->lt(now()->subHours((int) config('resume.updates.check_every_hours', 12)));
    }

    public function refreshIfStale(): void
    {
        if ($this->isStale()) {
            $this->check();
        }
    }

    /**
     * Asks GitHub for the latest release and saves the result.
     *
     * @return array{checked_at: ?Carbon, release: ?Release, error: ?string}
     */
    public function check(): array
    {
        $release = null;
        $error = null;
        $previous = $this->status()['release'];
        $etag = $previous !== null ? $this->etag() : null;
        $newEtag = $etag;

        try {
            $request = $this->api();
            $response = ($etag !== null ? $request->withHeaders(['If-None-Match' => $etag]) : $request)->get($this->apiUrl('releases/latest'));

            if ($response->status() === 304) {
                $release = $previous; // nothing new since the last check
            } elseif ($response->successful()) {
                $newEtag = $response->header('ETag') !== '' ? $response->header('ETag') : null;
                $release = Release::fromGitHub((array) $response->json());
                $error = $release === null ? __('admin.updates.errors.no_version') : null;
            } else {
                $error = $this->describe($response);
            }
        } catch (ConnectionException) {
            $error = __('admin.updates.errors.unreachable');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }

        $this->storage->writeJson($this->storage->checkFile(), [
            'checked_at' => now()->toAtomString(),
            // If GitHub does not respond, the last known release is kept
            'release' => ($release ?? $previous)?->toArray(),
            'error' => $error,
            'etag' => $newEtag,
        ]);

        return $this->status();
    }

    /**
     * Request to the GitHub API, with the token if there is one.
     */
    public function api(): PendingRequest
    {
        $request = Http::timeout(8)
            ->connectTimeout(4)
            ->withHeaders([
                'Accept' => 'application/vnd.github+json',
                'X-GitHub-Api-Version' => '2022-11-28',
                'User-Agent' => 'Resume-App/'.$this->updater->codeVersion(),
            ]);

        $token = $this->storage->token();

        return $token !== null ? $request->withToken($token) : $request;
    }

    public function apiUrl(string $path): string
    {
        return rtrim((string) config('resume.updates.api'), '/').'/repos/'.trim((string) config('resume.updates.repository'), '/').'/'.$path;
    }

    public function describe(Response $response): string
    {
        return match (true) {
            $response->status() === 401 => __('admin.updates.errors.token_invalid'),
            $response->status() === 403 && $response->header('X-RateLimit-Remaining') === '0' => __('admin.updates.errors.rate_limited'),
            $response->status() === 404 => __('admin.updates.errors.not_found'),
            default => __('admin.updates.errors.http', ['status' => $response->status()]),
        };
    }
}
