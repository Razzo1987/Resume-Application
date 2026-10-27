<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Support\Languages\Locales;
use App\Support\System\Backup;
use App\Support\System\Maintenance;
use App\Support\System\Updater;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Aggiornamento dall'admin, in tre tempi:
 *  1. start(): controlli sul pacchetto, esportazione dei contenuti, manutenzione, stato per lo script di estrazione;
 *  2. estrazione fuori da Laravel (index.php → resources/update/updater.php), a blocchi, con la sua pagina;
 *  3. finish(): con i file nuovi, migrazioni e cache (Updater::complete()), manutenzione com'era prima, pulizia.
 */
class UpdateRunner
{
    public function __construct(
        private readonly UpdateStorage $storage,
        private readonly PackageInspector $inspector,
        private readonly Backup $backup,
        private readonly Maintenance $maintenance,
        private readonly Updater $updater,
    ) {}

    /**
     * Prepara l'aggiornamento e restituisce l'indirizzo della pagina di avanzamento.
     *
     * @param  string  $returnUrl  dove tornare a estrazione finita (la pagina Sistema)
     * @param  string  $mode  "admin" (pagina di avanzamento nel browser) o "auto" (cron, vedi AutoUpdate)
     */
    public function start(string $package, string $returnUrl, string $mode = 'admin'): string
    {
        if ($this->storage->isExtracting()) {
            throw new RuntimeException((string) __('admin.updates.errors.in_progress'));
        }

        $report = $this->inspector->inspect($package);

        if (! $report->ok()) {
            throw new RuntimeException(implode("\n", $report->problems));
        }

        $zip = new ZipArchive;
        $zip->open($package, ZipArchive::RDONLY);
        $total = $zip->numFiles;
        $zip->close();

        $backup = $this->backup->create();
        $maintenanceWasOn = $this->maintenance->isOn();
        $this->maintenance->on();

        File::ensureDirectoryExists(dirname($this->storage->updaterFile()));
        File::copy(resource_path('update/updater.php'), $this->storage->updaterFile());

        $token = bin2hex(random_bytes(24));

        $this->storage->writeJson($this->storage->runFile(), [
            'from' => $report->currentVersion,
            'to' => $report->version,
            'package' => $package,
            'backup' => basename($backup),
            'maintenance_was_on' => $maintenanceWasOn,
            'mode' => $mode,
            'started_at' => now()->toAtomString(),
        ]);

        // Per ultimo: da qui index.php passa le richieste allo script di estrazione
        $this->storage->writeJson($this->storage->stateFile(), [
            'token' => $token,
            'zip' => $package,
            'next' => 0,
            'total' => $total,
            'from' => $report->currentVersion,
            'to' => $report->version,
            'return' => $returnUrl,
            'strings' => $this->strings(),
        ]);

        return rtrim(url('/'), '/').'/?resume-update='.$token;
    }

    /**
     * Estrazione finita, da completare (i file nuovi ci sono, il database non ancora).
     */
    public function isExtracted(): bool
    {
        return is_file($this->storage->runFile()) && ! $this->storage->isExtracting();
    }

    /**
     * Dati dell'aggiornamento in corso o da completare (versioni, modo, copia dei contenuti).
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->storage->readJson($this->storage->runFile());
    }

    /**
     * Indirizzo della pagina di avanzamento dell'estrazione in corso (con il suo codice), o null.
     */
    public function progressUrl(): ?string
    {
        $state = $this->storage->readJson($this->storage->stateFile());

        return is_string($state['token'] ?? null) ? rtrim(url('/'), '/').'/?resume-update='.$state['token'] : null;
    }

    /**
     * Completa l'aggiornamento: anche dopo un'estrazione fatta a mano (FTP, File Manager).
     *
     * @return list<string>
     */
    public function finish(): array
    {
        $log = $this->updater->complete();
        $run = $this->storage->readJson($this->storage->runFile());

        if ($run !== null && ! $this->storage->isExtracting()) {
            if (! ($run['maintenance_was_on'] ?? false)) {
                $this->maintenance->off();
                $log[] = 'maintenance: off';
            }

            $package = is_string($run['package'] ?? null) ? $run['package'] : '';

            // Il pacchetto non serve più (è stato scaricato o caricato qui); l'esportazione dei contenuti resta
            if ($package !== '' && dirname($package) === $this->storage->packagesDirectory()) {
                File::delete($package);
            }

            File::delete([$this->storage->updaterFile(), $this->storage->runFile()]);
            $log[] = 'backup: '.($run['backup'] ?? '-');
        }

        return $log;
    }

    /**
     * Testi della pagina di avanzamento (lingua dell'admin) e della pagina per i visitatori (lingua del sito).
     *
     * @return array<string, string>
     */
    private function strings(): array
    {
        $site = app(Locales::class)->default();

        return [
            'lang' => app()->getLocale(),
            'title' => (string) __('admin.updates.page.title'),
            'intro' => (string) __('admin.updates.page.intro'),
            'error' => (string) __('admin.updates.page.error'),
            'retry' => (string) __('admin.updates.page.retry'),
            'done' => (string) __('admin.updates.page.done'),
            'visitor_lang' => $site,
            'visitor_title' => (string) __('site.errors.maintenance_title', [], $site),
            'visitor_text' => (string) __('site.errors.maintenance_text', [], $site),
        ];
    }
}
