<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Support\System\Updater;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
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
        private readonly GitHub $github,
    ) {}

    public function repository(): string
    {
        return trim((string) config('resume.updates.repository'), '/');
    }

    /**
     * Result of the last check, without calling GitHub.
     *
     * @return array{checked_at: ?Carbon, release: ?Release, error: ?string, problem: ?string}
     */
    public function status(): array
    {
        $data = $this->storage->readJson($this->storage->checkFile()) ?? [];
        $problem = is_string($data['problem'] ?? null) ? $data['problem'] : null;

        return [
            'checked_at' => is_string($data['checked_at'] ?? null) ? Carbon::parse($data['checked_at']) : null,
            'release' => is_array($data['release'] ?? null) ? Release::fromArray($data['release']) : null,
            // Problems with a code are translated now, in the language of who reads them
            'error' => $problem !== null && $problem !== 'other'
                ? $this->github->message($problem, is_int($data['status'] ?? null) ? $data['status'] : null)
                : (is_string($data['error'] ?? null) ? $data['error'] : null),
            'problem' => $problem,
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
     * @return array{checked_at: ?Carbon, release: ?Release, error: ?string, problem: ?string}
     */
    public function check(): array
    {
        $release = null;
        $error = null;
        $problem = null;
        $status = null;
        $previous = $this->status()['release'];
        $etag = $previous !== null ? $this->etag() : null;
        $newEtag = $etag;

        try {
            $response = $this->github->get($this->repository(), 'releases/latest', $etag !== null ? ['If-None-Match' => $etag] : []);

            if ($response->status() === 304) {
                $release = $previous; // nothing new since the last check
            } elseif ($response->successful()) {
                $newEtag = $response->header('ETag') !== '' ? $response->header('ETag') : null;
                $release = Release::fromGitHub((array) $response->json());
                [$problem, $error] = $release === null ? ['other', (string) __('admin.updates.errors.no_version')] : [null, null];
            } else {
                $problem = $this->github->repository($this->repository())['problem'] ?? 'http';
                $status = $response->status();
                $error = $this->github->message($problem, $status);
            }
        } catch (ConnectionException) {
            $problem = 'unreachable';
            $error = $this->github->message($problem);
        } catch (Throwable $exception) {
            $problem = 'other';
            $error = $exception->getMessage();
        }

        $this->storage->writeJson($this->storage->checkFile(), [
            'checked_at' => now()->toAtomString(),
            // If GitHub does not respond, the last known release is kept
            'release' => ($release ?? $previous)?->toArray(),
            'error' => $error,
            'problem' => $problem,
            'status' => $status,
            'etag' => $newEtag,
        ]);

        return $this->status();
    }

    /**
     * Message for a failed response from the app's repository.
     */
    public function describe(Response $response): string
    {
        return $this->github->describe($response, $this->repository());
    }
}
