<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Filament\Pages\ManageSystem;
use App\Models\User;
use App\Support\Cron\Cron;
use App\Support\Settings\Settings;
use App\Support\System\Updater;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Throwable;
use ZipArchive;

/**
 * Aggiornamenti col cron, secondo l'impostazione scelta in Sistema:
 *  - off: solo il controllo delle nuove versioni (avviso in dashboard);
 *  - download: il pacchetto viene scaricato sul server e l'amministratore lo installa con un clic;
 *  - patch: le versioni di correzione (stessa x.y, es. 2.1.0 → 2.1.1) si installano da sole; quelle che aggiungono
 *    funzioni (2.2.0) restano da installare a mano, perché possono cambiare contenuti o temi.
 *
 * L'installazione automatica fa gli stessi passi dell'admin (controlli, copia dei contenuti, manutenzione,
 * estrazione con resources/update/updater.php), ma l'estrazione gira nel processo del cron. Il completamento
 * (migrazioni, cache, fine della manutenzione) parte in un processo nuovo, con il codice nuovo: subito se l'hosting
 * lo permette, altrimenti al passaggio successivo del cron. Esito in dashboard e per email agli amministratori.
 */
class AutoUpdate
{
    public const string OFF = 'off';

    public const string DOWNLOAD = 'download';

    public const string PATCH = 'patch';

    public const string SETTING = 'updates.auto';

    /** Tentativi di installazione automatica della stessa versione prima di lasciarla all'amministratore. */
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
     * Lavoro "updates" del cron. Dall'admin (senza cron) solo il controllo su GitHub.
     */
    public function run(bool $fromCron): ?string
    {
        if (! $this->inspector->isPackagedInstall()) {
            return 'development copy';
        }

        $run = $this->runner->current();

        // Estrazione automatica interrotta (tempo massimo, errore): riprende da dove era arrivata
        if ($this->storage->isExtracting()) {
            return $fromCron && ($run['mode'] ?? null) === 'auto' ? $this->extract() : 'extracting';
        }

        // File nuovi già al loro posto: si completa (questo processo è partito dopo l'estrazione, col codice nuovo)
        if ($this->runner->isExtracted() && ($run['mode'] ?? null) === 'auto') {
            return $this->finish();
        }

        $this->checker->refreshIfStale();
        $release = $this->checker->available();

        if ($release === null) {
            return 'up to date';
        }

        if (! $fromCron || $this->level() === self::OFF || $this->updater->isPending()) {
            return 'available '.$release->version;
        }

        $package = $this->download($release);

        if ($this->level() !== self::PATCH || ! $this->isPatch($release->version)) {
            return 'downloaded '.$release->version;
        }

        return $this->install($release, $package);
    }

    /**
     * Versione di correzione: stessa x.y di quella installata, z più alta.
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
     * Ultimo evento degli aggiornamenti automatici (per la dashboard).
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
     * Scarica tutto il pacchetto (dal cron non ci sono limiti di tempo per richiesta).
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

        $this->notify('downloaded', $release->version);

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
     * Estrae tutti i file rimasti. Da qui in poi questo processo non carica più classi dell'app (stanno cambiando
     * sotto di lui): scrive solo file e, alla fine, avvia il completamento in un processo nuovo.
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
        // Preparato prima: dopo l'inizio dell'estrazione niente classi dell'app, nemmeno per l'email
        $failedSubject = $this->subject('failed', $version);
        $failedBody = $this->body('failed', $version, null, $progress);
        $recipients = $this->recipients();
        $from = (string) config('mail.from.address');

        require_once $this->storage->updaterFile();
        @set_time_limit(0);

        try {
            do {
                $result = resume_updater_step($state, $webroot, $core, 30.0, 1_000_000);
            } while (! $result['done']);
        } catch (Throwable $exception) {
            // Solo PHP puro: il sito resta in "aggiornamento" e il cron riprova; l'amministratore ha il link per
            // continuare dal browser
            @file_put_contents($eventFile, json_encode(['last' => [
                'type' => 'failed', 'version' => $version, 'at' => date(DATE_ATOM), 'message' => $exception->getMessage(), 'url' => $progress,
            ]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            foreach ($recipients as $email) {
                @mail($email, $failedSubject, $failedBody."\n\n".$exception->getMessage(), $from !== '' ? 'From: '.$from : '');
            }

            throw new CronStop('extraction failed: '.$exception->getMessage(), ok: false);
        }

        // Completamento con il codice nuovo, in un processo separato; se exec() non c'è, al prossimo giro del cron
        if (function_exists('exec')) {
            @exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($artisan).' resume:update --finish > /dev/null 2>&1 &');
        }

        throw new CronStop('extracted '.$version);
    }

    /**
     * Completa un aggiornamento automatico (migrazioni, cache, manutenzione com'era) e avvisa.
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
     * I requisiti del pacchetto valgono anche per il PHP del sito, che può essere diverso da quello del cron.
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
     * Evento per la dashboard ed email agli amministratori.
     */
    private function notify(string $type, string $version, ?string $message = null): void
    {
        $url = $type === 'failed' ? $this->runner->progressUrl() : null;
        $data = $this->storage->readJson($this->eventFile()) ?? [];
        $data['last'] = ['type' => $type, 'version' => $version, 'at' => now()->toAtomString(), 'message' => $message, 'url' => $url];
        $this->storage->writeJson($this->eventFile(), $data);

        $this->mail($this->subject($type, $version), $this->body($type, $version, $message, $url));
    }

    private function subject(string $type, string $version): string
    {
        return '['.config('app.name').'] '.__('admin.updates.auto.mail.'.$type.'_subject', ['version' => $version]);
    }

    private function body(string $type, string $version, ?string $message, ?string $url): string
    {
        $lines = [(string) __('admin.updates.auto.mail.'.$type, ['version' => $version, 'site' => url('/')])];

        if ($url !== null) {
            $lines[] = (string) __('admin.updates.auto.mail.resume', ['url' => $url]);
        }

        $lines[] = (string) __('admin.updates.auto.mail.admin', ['url' => ManageSystem::getUrl()]);

        if ($message !== null && $message !== '' && $type !== 'installed') {
            $lines[] = $message;
        }

        return implode("\n\n", $lines);
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        try {
            return User::query()->pluck('email')->filter()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Email a tutti gli amministratori; se l'hosting non spedisce, resta l'avviso in dashboard.
     */
    private function mail(string $subject, string $body): void
    {
        try {
            foreach ($this->recipients() as $email) {
                Mail::raw($body, fn ($message) => $message->to($email)->subject($subject));
            }
        } catch (Throwable) {
            // nessuna email: l'esito resta in dashboard e in Sistema
        }
    }
}
