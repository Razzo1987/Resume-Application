<?php

declare(strict_types=1);

namespace App\Support\Cron;

use App\Support\Update\CronStop;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Lavori programmati: controllo degli aggiornamenti, aggiornamento automatico, database GeoLite, statistiche, pulizia.
 *
 * Con il cron dell'hosting (una riga in cPanel: "php …/core/artisan schedule:run" ogni 5-15 minuti) girano tutti,
 * anche quelli lunghi (download e installazione). Senza cron partono quelli leggeri dall'admin, dopo aver risposto,
 * al massimo una volta l'ora: il sito funziona comunque, ma ciò che richiede tempo si fa a mano dall'admin.
 *
 * Lo stato (ultimo passaggio del cron, esito di ogni lavoro) sta in core/storage/app/private/cron/state.json.
 */
class Cron
{
    /** Il cron è considerato attivo se è passato da meno di questi minuti. */
    public const int ACTIVE_MINUTES = 120;

    /** Senza cron, ogni quanto l'admin può far partire i lavori leggeri. */
    public const int FALLBACK_MINUTES = 60;

    /** @var array<string, array{every: int, light: bool, run: Closure(bool): ?string}> */
    private array $jobs = [];

    /**
     * @param  string|null  $storage  cartella storage dell'app (di default quella di Laravel; serve ai test)
     */
    public function __construct(private readonly ?string $storage = null) {}

    /**
     * Registra un lavoro (dai service provider).
     *
     * @param  int  $every  minuti tra un'esecuzione e l'altra
     * @param  bool  $light  breve: può partire anche dall'admin quando il cron non c'è
     * @param  Closure(bool): ?string  $run  riceve true dal cron (niente limiti di tempo); restituisce una nota o null
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
     * Esegue i lavori da fare. Dal cron tutti, dall'admin solo i leggeri. Un solo giro alla volta (lock).
     *
     * @param  string|null  $only  un solo lavoro, anche se non è ancora il momento
     * @return array<string, string> lavoro → esito ("ok", nota o "error: …")
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
     * PHP del sito (versione ed estensioni), annotato dalle pagine dell'admin al massimo una volta al giorno:
     * l'installazione automatica lo confronta con i requisiti del pacchetto, perché il cron può usare un altro PHP.
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
     * Senza cron attivo, fa partire i lavori leggeri dopo aver risposto all'admin (al massimo una volta l'ora).
     *
     * @return bool true se il giro è stato programmato
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
     * Il cron dell'hosting è passato di recente?
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
     * Versione di PHP usata dal cron (può essere diversa da quella del sito).
     */
    public function cronPhpVersion(): ?string
    {
        $version = $this->state()['cron']['php'] ?? null;

        return is_string($version) ? $version : null;
    }

    /**
     * Esito dell'ultima esecuzione di ogni lavoro.
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
     * Riga da inserire nei cron job di cPanel (ogni 5 minuti).
     */
    public function command(): string
    {
        return '*/5 * * * * '.$this->phpCli().' '.base_path('artisan').' schedule:run >/dev/null 2>&1';
    }

    /**
     * Percorso probabile del PHP da riga di comando. Il sito gira spesso con php-fpm o lsphp: su cPanel
     * il PHP per il cron sta accanto (es. /opt/cpanel/ea-php85/root/usr/bin/php).
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
            // File dell'app appena sostituiti: si registra l'esito e si chiude il giro
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
