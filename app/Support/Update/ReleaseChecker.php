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
 * Nuove versioni pubblicate su GitHub. Il controllo si fa al massimo ogni 12 ore (all'apertura della dashboard,
 * dopo aver risposto) o con "Controlla ora"; l'esito resta in core/storage, così la dashboard non aspetta GitHub.
 */
class ReleaseChecker
{
    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly Updater $updater,
    ) {}

    /**
     * Esito dell'ultimo controllo, senza chiamare GitHub.
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
     * La release più recente, se è più nuova della versione installata (in base all'ultimo controllo).
     */
    public function available(): ?Release
    {
        $release = $this->status()['release'];

        return $release !== null && $this->isNewer($release->version) ? $release : null;
    }

    public function isNewer(string $version): bool
    {
        $current = $this->updater->codeVersion();

        // Copia di sviluppo (VERSION "dev" o "2.1.0-dev"): nessun avviso
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
     * Chiede a GitHub l'ultima release e salva l'esito.
     *
     * @return array{checked_at: ?Carbon, release: ?Release, error: ?string}
     */
    public function check(): array
    {
        $release = null;
        $error = null;

        try {
            $response = $this->api()->get($this->apiUrl('releases/latest'));

            if ($response->successful()) {
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

        $previous = $this->status()['release'];

        $this->storage->writeJson($this->storage->checkFile(), [
            'checked_at' => now()->toAtomString(),
            // Se GitHub non risponde si tiene l'ultima release nota
            'release' => ($release ?? $previous)?->toArray(),
            'error' => $error,
        ]);

        return $this->status();
    }

    /**
     * Richiesta all'API di GitHub, con il token se c'è.
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
