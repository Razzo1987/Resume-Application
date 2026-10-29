<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Filament\Pages\ManageSystem;
use App\Models\User;
use App\Support\Cron\Cron;
use App\Support\Languages\AdminLocales;
use App\Support\Settings\Settings;
use App\Support\System\Updater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use ZipArchive;

/**
 * Updates via the cron, according to the setting chosen in System:
 *  - off: only the check for new versions (notice on the dashboard);
 *  - download: the package is downloaded to the server and the administrator installs it with one click;
 *  - patch: patch versions (same x.y, e.g. 2.1.0 → 2.1.1) install themselves; those that add features (2.2.0)
 *    still have to be installed by hand, because they can change content or themes.
 *
 * The automatic installation takes the same steps as the admin (checks, content backup, maintenance, extraction
 * with resources/update/updater.php), but the extraction runs in the cron process. The completion (migrations,
 * cache, end of maintenance) starts in a new process, with the new code: right away if the hosting allows it,
 * otherwise on the next cron run. Result on the dashboard and by email to the administrators.
 */
class AutoUpdate
{
    public const string OFF = 'off';

    public const string DOWNLOAD = 'download';

    public const string PATCH = 'patch';

    public const string SETTING = 'updates.auto';

    /** Automatic installation attempts for the same version before leaving it to the administrator. */
    public const int MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly Settings $settings,
        private readonly UpdateStorage $storage,
        private readonly ReleaseChecker $checker,
        private readonly ReleaseDownloader $downloader,
        private readonly PackageInspector $inspector,
        private readonly UpdateRunner $runner,
        private readonly Updater $updater,
        private readonly Cron $cron,
    ) {}

    public function level(): string
    {
        $level = $this->settings->get(self::SETTING, self::DOWNLOAD);

        return in_array($level, [self::OFF, self::DOWNLOAD, self::PATCH], true) ? $level : self::DOWNLOAD;
    }

    /**
     * The cron's "updates" job. From the admin (without cron) only the GitHub check.
     */
    public function run(bool $fromCron): ?string
    {
        if (! $this->inspector->isPackagedInstall()) {
            return 'development copy';
        }

        $run = $this->runner->current();

        // Interrupted automatic extraction (time limit, error): resume from where it stopped
        if ($this->storage->isExtracting()) {
            return $fromCron && ($run['mode'] ?? null) === 'auto' ? $this->extract() : 'extracting';
        }

        // New files already in place: finish the update (this process started after the extraction, with the new code)
        if ($this->runner->isExtracted() && ($run['mode'] ?? null) === 'auto') {
            return $this->finish();
        }

        // From the cron at every run (conditional request, cheap); from the admin at most every 12 hours
        $fromCron ? $this->checker->check() : $this->checker->refreshIfStale();
        $release = $this->checker->available();

        if ($release === null) {
            return 'up to date';
        }

        if (! $fromCron || $this->level() === self::OFF || $this->updater->isPending()) {
            // Without automatic download the administrators still get one email per new version
            if ($fromCron) {
                $this->mailOnce('available', $release);
            }

            return 'available '.$release->version;
        }

        $package = $this->download($release);

        if ($this->level() !== self::PATCH || ! $this->isPatch($release->version)) {
            return 'downloaded '.$release->version;
        }

        return $this->install($release, $package);
    }

    /**
     * Patch version: same x.y as the installed one, higher z.
     */
    public function isPatch(string $version): bool
    {
        $current = $this->updater->codeVersion();

        return preg_match(Release::VERSION_PATTERN, $current) === 1
            && preg_match(Release::VERSION_PATTERN, $version) === 1
            && implode('.', array_slice(explode('.', $version), 0, 2)) === implode('.', array_slice(explode('.', $current), 0, 2))
            && version_compare($version, $current, '>');
    }

    /**
     * Last event of the automatic updates (for the dashboard).
     *
     * @return array{type: string, version: string, at: Carbon, message: ?string, url: ?string}|null
     */
    public function lastEvent(): ?array
    {
        $event = $this->storage->readJson($this->eventFile())['last'] ?? null;

        if (! is_array($event) || ! is_string($event['type'] ?? null) || ! is_string($event['at'] ?? null)) {
            return null;
        }

        return [
            'type' => $event['type'],
            'version' => (string) ($event['version'] ?? ''),
            'at' => Carbon::parse($event['at']),
            'message' => is_string($event['message'] ?? null) ? $event['message'] : null,
            'url' => is_string($event['url'] ?? null) ? $event['url'] : null,
        ];
    }

    public function eventFile(): string
    {
        return $this->storage->directory().'/auto.json';
    }

    /**
     * Downloads the whole package (from the cron there is no per-request time limit).
     */
    private function download(Release $release): string
    {
        $existing = $this->storage->package($release->packageName());

        if ($existing !== null) {
            return $existing;
        }

        @set_time_limit(0);

        do {
            $result = $this->downloader->next($release);
        } while (! $result['done']);

        $this->notify('downloaded', $release->version, release: $release);

        return (string) $this->storage->package((string) $result['file']);
    }

    private function install(Release $release, string $package): string
    {
        $attempts = $this->attempts($release->version);

        if ($attempts >= self::MAX_ATTEMPTS) {
            return 'skipped '.$release->version.' (too many attempts)';
        }

        $this->recordAttempt($release->version);
        $report = $this->inspector->inspect($package);
        $problems = [...$report->problems, ...$this->checkWebRuntime($package)];

        if ($problems !== []) {
            $this->notify('failed', $release->version, implode("\n", $problems));

            return 'not installed '.$release->version.': '.implode('; ', $problems);
        }

        try {
            $this->runner->start($package, ManageSystem::getUrl(['update' => 'finish']), 'auto');
        } catch (Throwable $exception) {
            $this->notify('failed', $release->version, $exception->getMessage());

            throw $exception;
        }

        return $this->extract();
    }

    /**
     * Extracts all remaining files. From here on this process no longer loads app classes (they are changing
     * underneath it): it only writes files and, at the end, starts the completion in a new process.
     */
    private function extract(): string
    {
        $run = $this->runner->current() ?? [];
        $version = (string) ($run['to'] ?? '');
        $state = $this->storage->stateFile();
        $webroot = $this->inspector->webroot();
        $core = $this->inspector->core();
        $artisan = $core.'/artisan';
        $eventFile = $this->eventFile();
        $progress = $this->runner->progressUrl();
        // Prepared beforehand: once the extraction has started, no app classes, not even for the email
        $failedMails = $this->messages('failed', $version, null, $progress, null);
        $from = (string) config('mail.from.address');

        require_once $this->storage->updaterFile();
        @set_time_limit(0);

        try {
            do {
                $result = resume_updater_step($state, $webroot, $core, 30.0, 1_000_000);
            } while (! $result['done']);
        } catch (Throwable $exception) {
            // Plain PHP only: the site stays in "updating" state and the cron retries; the administrator has the link
            // to continue from the browser
            @file_put_contents($eventFile, json_encode(['last' => [
                'type' => 'failed', 'version' => $version, 'at' => date(DATE_ATOM), 'message' => $exception->getMessage(), 'url' => $progress,
            ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            foreach ($failedMails as $email => $mail) {
                @mail($email, $mail['subject'], $mail['body']."\n\n".$exception->getMessage(), $from !== '' ? 'From: '.$from : '');
            }

            throw new CronStop('extraction failed: '.$exception->getMessage(), ok: false);
        }

        // Completion with the new code, in a separate process; if exec() is not available, on the next cron run
        if (function_exists('exec')) {
            @exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($artisan).' resume:update --finish > /dev/null 2>&1 &');
        }

        throw new CronStop('extracted '.$version);
    }

    /**
     * Completes an automatic update (migrations, cache, maintenance back as it was) and sends notices.
     */
    public function finish(): string
    {
        $run = $this->runner->current() ?? [];
        $version = (string) ($run['to'] ?? $this->updater->codeVersion());

        try {
            $log = $this->runner->finish();
        } catch (Throwable $exception) {
            $this->notify('failed', $version, $exception->getMessage());

            throw $exception;
        }

        $this->notify('installed', $version, implode("\n", $log));

        return 'installed '.$version;
    }

    /**
     * The package requirements also apply to the site's PHP, which can differ from the cron's.
     *
     * @return list<string>
     */
    private function checkWebRuntime(string $package): array
    {
        $web = $this->cron->webRuntime();
        $zip = new ZipArchive;

        if ($web === null || $zip->open($package, ZipArchive::RDONLY) !== true) {
            return $web === null ? [(string) __('admin.updates.auto.web_unknown')] : [];
        }

        /** @var array{requires?: array{php?: string, extensions?: list<string>}} $manifest */
        $manifest = json_decode((string) $zip->getFromName('core/manifest.json'), true) ?: [];
        $zip->close();
        $requires = $manifest['requires'] ?? [];
        $problems = [];

        if (isset($requires['php']) && version_compare($web['php'], $requires['php'], '<')) {
            $problems[] = (string) __('admin.updates.problems.php', ['required' => $requires['php'], 'current' => $web['php']]);
        }

        foreach ($requires['extensions'] ?? [] as $extension) {
            if (! in_array(strtolower($extension), $web['extensions'], true)) {
                $problems[] = (string) __('admin.updates.problems.extension', ['extension' => $extension]);
            }
        }

        return $problems;
    }

    private function attempts(string $version): int
    {
        $data = $this->storage->readJson($this->eventFile()) ?? [];

        return (int) ($data['attempts'][$version] ?? 0);
    }

    private function recordAttempt(string $version): void
    {
        $data = $this->storage->readJson($this->eventFile()) ?? [];
        $data['attempts'] = [$version => $this->attempts($version) + 1];
        $this->storage->writeJson($this->eventFile(), $data);
    }

    /**
     * Event for the dashboard and email to the administrators.
     */
    private function notify(string $type, string $version, ?string $message = null, ?Release $release = null): void
    {
        $url = $type === 'failed' ? $this->runner->progressUrl() : null;
        $data = $this->storage->readJson($this->eventFile()) ?? [];
        $data['last'] = ['type' => $type, 'version' => $version, 'at' => now()->toAtomString(), 'message' => $message, 'url' => $url];
        $this->storage->writeJson($this->eventFile(), $data);

        $this->mail($type, $version, $message, $url, $release);
    }

    /**
     * Email about a version sent only once (e.g. "new version available").
     */
    private function mailOnce(string $type, Release $release): void
    {
        $data = $this->storage->readJson($this->eventFile()) ?? [];

        if (($data['mailed'][$type] ?? null) === $release->version) {
            return;
        }

        $data['mailed'][$type] = $release->version;
        $this->storage->writeJson($this->eventFile(), $data);
        $this->mail($type, $release->version, null, null, $release);
    }

    /**
     * Subject and body for each administrator, in the language they use in the admin (the cron runs without one).
     *
     * @return array<string, array{subject: string, body: string}> email => message
     */
    private function messages(string $type, string $version, ?string $message, ?string $url, ?Release $release): array
    {
        $messages = [];

        foreach ($this->recipients() as $user) {
            $messages[(string) $user->email] = $this->inAdminLocale($user, fn (): array => [
                'subject' => $this->subject($type, $version),
                'body' => $this->body($type, $version, $message, $url, $release),
            ]);
        }

        return $messages;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function inAdminLocale(User $user, callable $callback): mixed
    {
        $previous = app()->getLocale();
        app()->setLocale(app(AdminLocales::class)->for($user));

        try {
            return $callback();
        } finally {
            app()->setLocale($previous);
        }
    }

    private function subject(string $type, string $version): string
    {
        return '['.config('app.name').'] '.__('admin.updates.auto.mail.'.$type.'_subject', ['version' => $version]);
    }

    private function body(string $type, string $version, ?string $message, ?string $url, ?Release $release): string
    {
        $lines = [(string) __('admin.updates.auto.mail.'.$type, ['version' => $version, 'site' => url('/')])];

        if ($url !== null) {
            $lines[] = (string) __('admin.updates.auto.mail.resume', ['url' => $url]);
        }

        // What to do next: for a new version, which button to press in System
        $lines[] = in_array($type, ['available', 'downloaded'], true)
            ? (string) __('admin.updates.auto.mail.'.$type.'_next', ['url' => ManageSystem::getUrl()])
            : (string) __('admin.updates.auto.mail.admin', ['url' => ManageSystem::getUrl()]);

        if ($release !== null && $release->url !== '') {
            $lines[] = (string) __('admin.updates.auto.mail.notes', ['url' => $release->url]);
        }

        if ($message !== null && $message !== '' && $type !== 'installed') {
            $lines[] = $message;
        }

        return implode("\n\n", $lines);
    }

    /**
     * @return list<User>
     */
    private function recipients(): array
    {
        try {
            return User::query()->whereNotNull('email')->get()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Email to all administrators; if the hosting does not send mail, the dashboard notice remains and the error
     * goes to the log (System page).
     */
    private function mail(string $type, string $version, ?string $message, ?string $url, ?Release $release): void
    {
        try {
            foreach ($this->messages($type, $version, $message, $url, $release) as $email => $mail) {
                Mail::raw($mail['body'], fn ($message) => $message->to($email)->subject($mail['subject']));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
