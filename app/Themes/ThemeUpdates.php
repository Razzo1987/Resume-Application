<?php

declare(strict_types=1);

namespace App\Themes;

use App\Filament\Pages\ManageUpdates;
use App\Models\User;
use App\Support\Languages\AdminLocales;
use App\Support\Update\GitHub;
use App\Support\Update\Release;
use App\Support\Update\Signature;
use App\Support\Update\UpdateStorage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Updates of the installed themes that declare where their releases are published (theme.json):
 *
 *   "updates": { "repository": "owner/resume-themes-pro", "tag_prefix": "folio-v" }
 *
 * The releases of a theme have tags "{tag_prefix}X.Y.Z" and the package "{name}-X.Y.Z.zip" (with an optional
 * "{name}-X.Y.Z.zip.sha256" and, for themes that declare "signing_keys", "{name}-X.Y.Z.zip.sig"). Private repositories need a GitHub key of the Updates page (a fine-grained token
 * with read access to the repository contents), chosen per repository by GitHub. One request per repository, conditional (ETag), so
 * several themes published in the same repository cost a single call and an unchanged answer is free.
 */
class ThemeUpdates
{
    private const int MAX_PACKAGE = 50 * 1024 * 1024;

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly ThemeInstaller $installer,
        private readonly UpdateStorage $storage,
        private readonly GitHub $github,
    ) {}

    public function file(): string
    {
        return $this->storage->directory().'/theme-updates.json';
    }

    /**
     * Installed themes with an update source: name => Theme.
     *
     * @return array<string, Theme>
     */
    public function updatable(): array
    {
        $themes = [];

        foreach (array_keys($this->themes->installed()) as $name) {
            $theme = $this->themes->get($name);

            if ($theme->updateRepository !== null && $name !== $this->themes->base()->name) {
                $themes[$name] = $theme;
            }
        }

        return $themes;
    }

    /**
     * Result of the last check, without calling GitHub.
     *
     * @return array{checked_at: ?Carbon, releases: array<string, Release>, errors: array<string, string>, problems: array<string, string>}
     */
    public function status(): array
    {
        $data = $this->storage->readJson($this->file()) ?? [];
        $releases = [];
        $errors = array_filter(is_array($data['errors'] ?? null) ? $data['errors'] : [], 'is_string');
        $problems = array_filter(is_array($data['problems'] ?? null) ? $data['problems'] : [], 'is_string');

        foreach (is_array($data['releases'] ?? null) ? $data['releases'] : [] as $name => $release) {
            if (is_string($name) && is_array($release)) {
                $releases[$name] = Release::fromArray($release);
            }
        }

        // Problems with a code are translated now, in the language of who reads them
        foreach ($problems as $name => $problem) {
            if (in_array($problem, GitHub::PROBLEMS, true)) {
                $errors[$name] = $this->github->message($problem, is_int($data['statuses'][$name] ?? null) ? $data['statuses'][$name] : null);
            }
        }

        return [
            'checked_at' => is_string($data['checked_at'] ?? null) ? Carbon::parse($data['checked_at']) : null,
            'releases' => $releases,
            'errors' => $errors,
            'problems' => $problems,
        ];
    }

    /**
     * Themes with a newer release than the installed version: name => Release.
     *
     * @return array<string, Release>
     */
    public function available(): array
    {
        $releases = $this->status()['releases'];
        $available = [];

        foreach ($this->updatable() as $name => $theme) {
            $release = $releases[$name] ?? null;

            if ($release !== null && version_compare($release->version, $theme->version, '>')) {
                $available[$name] = $release;
            }
        }

        return $available;
    }

    public function isStale(int $hours = 12): bool
    {
        $checked = $this->status()['checked_at'];

        return $checked === null || $checked->lt(now()->subHours($hours));
    }

    /**
     * Asks GitHub for the latest release of every updatable theme and saves the result.
     *
     * @return array{checked_at: ?Carbon, releases: array<string, Release>, errors: array<string, string>, problems: array<string, string>}
     */
    public function check(): array
    {
        $previous = $this->storage->readJson($this->file()) ?? [];
        $etags = is_array($previous['etags'] ?? null) ? $previous['etags'] : [];
        $listings = is_array($previous['listings'] ?? null) ? $previous['listings'] : [];
        $releases = [];
        $errors = [];
        $problems = [];
        $statuses = [];

        $byRepository = [];

        foreach ($this->updatable() as $name => $theme) {
            $byRepository[(string) $theme->updateRepository][$name] = $theme;
        }

        foreach ($byRepository as $repository => $themes) {
            $listing = null;
            $error = null;
            $problem = null;
            $status = null;

            try {
                $etag = is_string($etags[$repository] ?? null) && isset($listings[$repository]) ? $etags[$repository] : null;
                $response = $this->github->get($repository, 'releases?per_page=100', $etag !== null ? ['If-None-Match' => $etag] : []);

                if ($response->status() === 304) {
                    $listing = $listings[$repository];
                } elseif ($response->successful()) {
                    $listing = array_values(array_filter((array) $response->json(), 'is_array'));
                    $etags[$repository] = $response->header('ETag') !== '' ? $response->header('ETag') : null;
                } else {
                    // 404: private repository without a key, or a key without access to it
                    $problem = $this->github->repository($repository)['problem'] ?? 'http';
                    $status = $response->status();
                    $error = $this->github->message($problem, $status);
                }
            } catch (ConnectionException) {
                $problem = 'unreachable';
                $error = $this->github->message($problem);
            } catch (Throwable $exception) {
                $error = $exception->getMessage();
            }

            if ($listing !== null) {
                $listings[$repository] = $this->compact($listing);
            }

            foreach ($themes as $name => $theme) {
                if ($error !== null) {
                    $errors[$name] = $error;
                }

                if ($problem !== null) {
                    $problems[$name] = $problem;
                    $statuses[$name] = $status;
                }

                $latest = $this->latest($listings[$repository] ?? [], $theme);

                if ($latest !== null) {
                    $releases[$name] = $latest->toArray();
                } elseif (is_array($previous['releases'][$name] ?? null)) {
                    $releases[$name] = $previous['releases'][$name]; // GitHub not reachable: last known release
                }
            }
        }

        $this->storage->writeJson($this->file(), [
            'checked_at' => now()->toAtomString(),
            'releases' => $releases,
            'errors' => $errors,
            'problems' => $problems,
            'statuses' => $statuses,
            'etags' => array_intersect_key($etags, $byRepository),
            'listings' => array_intersect_key($listings, $byRepository),
            'mailed' => is_array($previous['mailed'] ?? null) ? $previous['mailed'] : [],
        ]);

        return $this->status();
    }

    /**
     * Scheduled job: from the cron a check at every run (conditional requests), from the admin at most every
     * 12 hours. The administrators get one email per theme and version.
     */
    public function run(bool $fromCron): string
    {
        if ($this->updatable() === []) {
            return 'no themes';
        }

        if ($fromCron || $this->isStale()) {
            $this->check();
        }

        $available = $this->available();

        foreach ($available as $name => $release) {
            $this->mailOnce($name, $release);
        }

        if ($available !== []) {
            return 'themes available '.implode(', ', array_map(
                fn (string $name, Release $release): string => "{$name} {$release->version}",
                array_keys($available),
                $available,
            ));
        }

        $errors = $this->status()['errors'];

        return $errors !== [] ? (string) reset($errors) : 'up to date';
    }

    /**
     * One email per theme and version to every administrator, in their admin language.
     */
    private function mailOnce(string $name, Release $release): void
    {
        $data = $this->storage->readJson($this->file()) ?? [];

        if (($data['mailed'][$name] ?? null) === $release->version) {
            return;
        }

        $data['mailed'][$name] = $release->version;
        $this->storage->writeJson($this->file(), $data);

        $theme = $this->themes->get($name);

        try {
            foreach (User::query()->whereNotNull('email')->get() as $user) {
                $previous = app()->getLocale();
                app()->setLocale(app(AdminLocales::class)->for($user));

                try {
                    $subject = '['.config('app.name').'] '.__('admin.themes.updates.mail.subject', ['theme' => $theme->title, 'version' => $release->version]);
                    $body = implode("\n\n", array_filter([
                        (string) __('admin.themes.updates.mail.body', ['theme' => $theme->title, 'version' => $release->version, 'installed' => $theme->version, 'site' => url('/')]),
                        (string) __('admin.themes.updates.mail.next', ['url' => ManageUpdates::getUrl()]),
                        $release->url !== '' ? (string) __('admin.updates.auto.mail.notes', ['url' => $release->url]) : null,
                    ]));
                } finally {
                    app()->setLocale($previous);
                }

                Mail::raw($body, fn ($message) => $message->to((string) $user->email)->subject($subject));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    /**
     * Downloads the latest release of a theme and installs it.
     *
     * @return array{theme: Theme, updated: bool, previous: string|null}
     */
    public function update(string $name): array
    {
        $release = $this->available()[$name] ?? throw new ThemeException((string) __('admin.themes.updates.errors.none'));
        $asset = $release->package ?? throw new ThemeException((string) __('admin.themes.updates.errors.no_package', ['version' => $release->version]));

        // Without the SHA-256 published with the release the package is not downloaded at all
        if ($release->checksum === null) {
            throw new ThemeException((string) __('admin.updates.errors.checksum_none', ['version' => $release->version]));
        }

        // A theme that declares its signing keys accepts only packages signed with them (the keys of the installed
        // version: a package cannot bring its own)
        $keys = $this->themes->get($name)->signingKeys;
        $signatureAsset = $keys !== []
            ? ($release->signature ?? throw new ThemeException((string) __('admin.updates.errors.signature_none', ['version' => $release->version])))
            : null;

        if ($asset['size'] > self::MAX_PACKAGE) {
            throw new ThemeException((string) __('admin.themes.errors.too_big'));
        }

        $file = $this->storage->packagesDirectory().'/theme-'.Str::lower(Str::random(8)).'.zip';
        $repository = (string) $this->themes->get($name)->updateRepository;
        File::ensureDirectoryExists(dirname($file));

        try {
            $response = $this->github->asset($repository, $asset);

            if (! $response->successful()) {
                throw new ThemeException($this->github->describe($response, $repository));
            }

            file_put_contents($file, $response->body());

            $checksum = $this->github->asset($repository, $release->checksum);

            if (! $checksum->successful() || preg_match('/^([a-f0-9]{64})\b/i', trim($checksum->body()), $match) !== 1
                || ! hash_equals(strtolower($match[1]), (string) hash_file('sha256', $file))) {
                throw new ThemeException((string) __('admin.updates.errors.checksum_mismatch'));
            }

            if ($signatureAsset !== null) {
                $signature = $this->github->asset($repository, $signatureAsset);

                if (! $signature->successful() || ! Signature::verify($file, $signature->body(), $keys)) {
                    throw new ThemeException((string) __('admin.updates.errors.signature_bad'));
                }
            }

            $results = collect($this->installer->installAll($file));
            $result = $results->first(fn (array $result): bool => $result['theme']->name === $name);

            if ($result === null) {
                throw new ThemeException((string) __('admin.themes.updates.errors.wrong_package', ['theme' => $name]));
            }

            return $result;
        } catch (ConnectionException) {
            throw new ThemeException((string) __('admin.updates.errors.unreachable'));
        } catch (RuntimeException $exception) {
            throw $exception instanceof ThemeException ? $exception : new ThemeException($exception->getMessage());
        } finally {
            File::delete($file);
        }
    }

    /**
     * Highest release of the theme in a listing (tag with the theme's prefix and its package attached).
     *
     * @param  array<array-key, mixed>  $listing
     */
    private function latest(array $listing, Theme $theme): ?Release
    {
        $latest = null;

        foreach ($listing as $data) {
            $release = is_array($data) ? Release::fromGitHub($data, $theme->updateTagPrefix, $theme->name) : null;

            if ($release !== null && $release->package !== null && ($latest === null || version_compare($release->version, $latest->version, '>'))) {
                $latest = $release;
            }
        }

        return $latest;
    }

    /**
     * Only what is needed of the GitHub listing (it is kept for the conditional requests).
     *
     * @param  list<array<array-key, mixed>>  $listing
     * @return list<array<string, mixed>>
     */
    private function compact(array $listing): array
    {
        return array_map(fn (array $release): array => [
            'tag_name' => $release['tag_name'] ?? null,
            'name' => $release['name'] ?? null,
            'body' => is_string($release['body'] ?? null) ? Str::limit($release['body'], 4000) : null,
            'html_url' => $release['html_url'] ?? null,
            'published_at' => $release['published_at'] ?? null,
            'draft' => $release['draft'] ?? false,
            'prerelease' => $release['prerelease'] ?? false,
            'assets' => array_map(fn (mixed $asset): array => is_array($asset) ? [
                'id' => $asset['id'] ?? 0,
                'name' => $asset['name'] ?? null,
                'size' => $asset['size'] ?? 0,
                'url' => $asset['url'] ?? '',
                'browser_download_url' => $asset['browser_download_url'] ?? '',
            ] : [], is_array($release['assets'] ?? null) ? $release['assets'] : []),
        ], $listing);
    }
}
