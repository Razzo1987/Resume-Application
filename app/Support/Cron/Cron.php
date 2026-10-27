<?php

declare(strict_types=1);

namespace App\Support\Cron;

use App\Support\Update\CronStop;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Scheduled jobs: update check, automatic update, GeoLite databases, statistics, clean-up.
 *
 * With the hosting cron (one line in the control panel: "php …/core/artisan schedule:run" every 5-15 minutes) they
 * all run, the long ones too (download and installation). Without cron the light ones start from the admin, after
 * the response is sent, at most once an hour: the site still works, but whatever takes time is done by hand.
 *
 * The state (last cron run, result of each job) is in core/storage/app/private/cron/state.json.
 */
class Cron
{
    /** The cron is considered active if it last ran less than this many minutes ago. */
    public const int ACTIVE_MINUTES = 120;

    /** Without cron, how often the admin can start the light jobs. */
    public const int FALLBACK_MINUTES = 60;

    /** @var array<string, array{every: int, light: bool, run: Closure(bool): ?string}> */
    private array $jobs = [];

    /**
     * @param  string|null  $storage  the app's storage folder (Laravel's by default; used by the tests)
     */
    public function __construct(private readonly ?string $storage = null) {}

    /**
     * Registers a job (from the service providers).
     *
     * @param  int  $every  minutes between one run and the next
     * @param  bool  $light  short: it can also start from the admin when there is no cron
     * @param  Closure(bool): ?string  $run  receives true from the cron (no time limits); returns a note or null
     */
    public function register(string $name, int $every, bool $light, Closure $run): void
    {
        $this->jobs[$name] = ['every' => $every, 'light' => $light, 'run' => $run];
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->jobs);
    }

    /**
     * Runs the due jobs: all of them from the cron, only the light ones from the admin. One run at a time (lock).
     *
     * @param  string|null  $only  a single job, even if it is not due yet
     * @return array<string, string> job → result ("ok", a note or "error: …")
     */
    public function run(bool $fromCron, ?string $only = null): array
    {
        File::ensureDirectoryExists($this->directory());
        $lock = fopen($this->directory().'/cron.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            return ['cron' => 'busy'];
        }

        $results = [];
        $stop = false;

        try {
            if ($fromCron) {
                $this->update(fn (array $state): array => [...$state, 'cron' => [
                    'at' => now()->toAtomString(),
                    'php' => PHP_VERSION,
                    'binary' => PHP_BINARY,
                ]]);
            } else {
                $this->update(fn (array $state): array => [...$state, 'fallback_at' => now()->toAtomString()]);
            }

            foreach ($this->jobs as $name => $job) {
                if ($only !== null ? $name !== $only : (! $this->isDue($name) || (! $fromCron && ! $job['light']))) {
                    continue;
                }

                $results[$name] = $this->execute($name, $job['run'], $fromCron, $stop);

                if ($stop) {
                    break;
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }

        return $results;
    }

    /**
     * The site's PHP (version and extensions), recorded by the admin pages at most once a day: the automatic
     * installation compares it with the package requirements, because the cron can use a different PHP.
     */
    public function rememberWebRuntime(): void
    {
        $web = $this->state()['web'] ?? null;

        if (is_array($web) && ($web['php'] ?? null) === PHP_VERSION && is_string($web['at'] ?? null)
            && Carbon::parse($web['at'])->gt(now()->subDay())) {
            return;
        }

        $this->update(fn (array $state): array => [...$state, 'web' => [
            'at' => now()->toAtomString(),
            'php' => PHP_VERSION,
            'extensions' => array_map(strtolower(...), get_loaded_extensions()),
        ]]);
    }

    /**
     * @return array{php: string, extensions: list<string>}|null
     */
    public function webRuntime(): ?array
    {
        $web = $this->state()['web'] ?? null;

        if (! is_array($web) || ! is_string($web['php'] ?? null) || ! is_array($web['extensions'] ?? null)) {
            return null;
        }

        return ['php' => $web['php'], 'extensions' => array_values(array_map(strval(...), $web['extensions']))];
    }

    /**
     * Without an active cron, starts the light jobs after responding to the admin (at most once an hour).
     *
     * @return bool true if the run was scheduled
     */
    public function runFromAdminIfNeeded(): bool
    {
        if ($this->isActive()) {
            return false;
        }

        $last = $this->state()['fallback_at'] ?? null;

        if (is_string($last) && Carbon::parse($last)->gt(now()->subMinutes(self::FALLBACK_MINUTES))) {
            return false;
        }

        app()->terminating(fn () => rescue(fn () => $this->run(fromCron: false), report: false));

        return true;
    }

    public function isDue(string $name): bool
    {
        $job = $this->jobs[$name] ?? null;
        $last = $this->state()['jobs'][$name]['at'] ?? null;

        return $job !== null && (! is_string($last) || Carbon::parse($last)->lte(now()->subMinutes($job['every'])->addSeconds(30)));
    }

    /**
     * Has the hosting cron run recently?
     */
    public function isActive(): bool
    {
        $at = $this->lastCron();

        return $at !== null && $at->gt(now()->subMinutes(self::ACTIVE_MINUTES));
    }

    public function lastCron(): ?Carbon
    {
        $at = $this->state()['cron']['at'] ?? null;

        return is_string($at) ? Carbon::parse($at) : null;
    }

    /**
     * PHP version used by the cron (it can differ from the site's).
     */
    public function cronPhpVersion(): ?string
    {
        $version = $this->state()['cron']['php'] ?? null;

        return is_string($version) ? $version : null;
    }

    /**
     * Result of the last run of each job.
     *
     * @return array<string, array{at: ?Carbon, ok: bool, message: ?string, every: int, light: bool}>
     */
    public function jobs(): array
    {
        $state = $this->state()['jobs'] ?? [];
        $list = [];

        foreach ($this->jobs as $name => $job) {
            $last = is_array($state[$name] ?? null) ? $state[$name] : [];

            $list[$name] = [
                'at' => is_string($last['at'] ?? null) ? Carbon::parse($last['at']) : null,
                'ok' => (bool) ($last['ok'] ?? true),
                'message' => is_string($last['message'] ?? null) ? $last['message'] : null,
                'every' => $job['every'],
                'light' => $job['light'],
            ];
        }

        return $list;
    }

    /**
     * Records the result of a job finished elsewhere (e.g. "resume:update --finish" started after the extraction).
     */
    public function recordResult(string $name, bool $ok, ?string $message): void
    {
        $this->update(function (array $state) use ($name, $ok, $message): array {
            $state['jobs'][$name] = [...(is_array($state['jobs'][$name] ?? null) ? $state['jobs'][$name] : []), 'at' => now()->toAtomString(), 'ok' => $ok, 'message' => $message];

            return $state;
        });
    }

    /** Job results (in English in the log) → text for the admin */
    private const array RESULTS = [
        '/^off$/' => 'off',
        '/^up to date$/' => 'up_to_date',
        '/^development copy$/' => 'development',
        '/^extracting$/' => 'extracting',
        '/^available (?<version>\S+)$/' => 'available',
        '/^downloaded (?<version>\S+)$/' => 'downloaded',
        '/^extracted (?<version>\S+)$/' => 'extracted',
        '/^installed (?<version>\S+)$/' => 'installed',
        '/^not installed (?<version>\S+): (?<reason>.*)$/s' => 'not_installed',
        '/^skipped (?<version>\S+) \(too many attempts\)$/' => 'skipped',
        '/^extraction failed: (?<reason>.*)$/s' => 'extraction_failed',
        '/^deleted (?<count>\d+)$/' => 'deleted',
        '/^days (?<days>\d+), deleted (?<visits>\d+) visits, (?<ips>\d+) admin IPs$/' => 'stats',
        '/^update available$/' => 'geoip_available',
        '/^no MaxMind account$/' => 'no_account',
        '/^updated (?<editions>.+)$/' => 'geoip_updated',
        '/^busy$/' => 'busy',
    ];

    /**
     * A job's result worded for the admin (errors stay as they are).
     */
    public static function describe(?string $message): string
    {
        if ($message === null || $message === '') {
            return '';
        }

        foreach (self::RESULTS as $pattern => $key) {
            if (preg_match($pattern, $message, $match) === 1) {
                $params = array_filter($match, is_string(...), ARRAY_FILTER_USE_KEY);

                return isset($params['count'])
                    ? trans_choice('admin.cron.results.'.$key, (int) $params['count'], $params)
                    : (string) __('admin.cron.results.'.$key, $params);
            }
        }

        return $message;
    }

    /**
     * Line to add to the cron jobs of the hosting (every 5 minutes).
     */
    public function command(): string
    {
        return '*/5 * * * * '.$this->phpCli().' '.base_path('artisan').' schedule:run >/dev/null 2>&1';
    }

    /**
     * Likely path of the command line PHP. The site often runs with php-fpm or lsphp, and the hosting panels keep
     * the CLI binary of each PHP version in a known place (cPanel EasyApache, CloudLinux alt-php, Plesk, DirectAdmin).
     */
    public function phpCli(): string
    {
        $binary = PHP_BINARY;

        if (PHP_SAPI === 'cli') {
            return $binary;
        }

        $candidates = [dirname($binary).'/php', dirname($binary, 2).'/bin/php'];
        $candidates[] = sprintf('/opt/cpanel/ea-php%d%d/root/usr/bin/php', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
        $candidates[] = sprintf('/opt/alt/php%d%d/usr/bin/php', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
        $candidates[] = sprintf('/opt/plesk/php/%d.%d/bin/php', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);
        $candidates[] = sprintf('/usr/local/php%d%d/bin/php', PHP_MAJOR_VERSION, PHP_MINOR_VERSION);

        foreach ($candidates as $candidate) {
            if (rescue(fn (): bool => @is_file($candidate) && @is_executable($candidate), false, report: false)) {
                return $candidate;
            }
        }

        return 'php';
    }

    public function directory(): string
    {
        return rtrim($this->storage ?? storage_path(), '/').'/app/private/cron';
    }

    public function stateFile(): string
    {
        return $this->directory().'/state.json';
    }

    /**
     * @param  Closure(bool): ?string  $run
     */
    private function execute(string $name, Closure $run, bool $fromCron, bool &$stop): string
    {
        $started = microtime(true);

        try {
            $message = $run($fromCron);
            $ok = true;
        } catch (CronStop $signal) {
            // App files just replaced: record the result and end the run
            $message = $signal->getMessage();
            $ok = $signal->ok;
            $stop = true;
        } catch (Throwable $exception) {
            report($exception);
            $message = $exception->getMessage();
            $ok = false;
        }

        $this->update(function (array $state) use ($name, $ok, $message, $started): array {
            $state['jobs'][$name] = [
                'at' => now()->toAtomString(),
                'ok' => $ok,
                'message' => $message,
                'seconds' => round(microtime(true) - $started, 2),
            ];

            return $state;
        });

        return $ok ? ($message ?? 'ok') : 'error: '.$message;
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $file = $this->stateFile();
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : [];
    }

    /**
     * @param  Closure(array<string, mixed>): array<string, mixed>  $change
     */
    private function update(Closure $change): void
    {
        File::ensureDirectoryExists($this->directory());
        $file = $this->stateFile();
        file_put_contents($file.'.tmp', json_encode($change($this->state()), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($file.'.tmp', $file);
    }
}
