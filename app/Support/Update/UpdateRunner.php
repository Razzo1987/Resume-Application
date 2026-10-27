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
 * Update from the admin, in three steps:
 *  1. start(): package checks, content export, maintenance, state for the extraction script;
 *  2. extraction outside Laravel (index.php → resources/update/updater.php), in chunks, with its own page;
 *  3. finish(): with the new files, migrations and cache (Updater::complete()), maintenance back as it was, cleanup.
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
     * Prepares the update and returns the URL of the progress page.
     *
     * @param  string  $returnUrl  where to return once the extraction is done (the System page)
     * @param  string  $mode  "admin" (progress page in the browser) or "auto" (cron, see AutoUpdate)
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

        // Last: from here on, index.php hands requests over to the extraction script
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
     * Extraction finished, still to be completed (the new files are in place, the database is not yet).
     */
    public function isExtracted(): bool
    {
        return is_file($this->storage->runFile()) && ! $this->storage->isExtracting();
    }

    /**
     * Data of the update in progress or still to be completed (versions, mode, content backup).
     *
     * @return array<string, mixed>|null
     */
    public function current(): ?array
    {
        return $this->storage->readJson($this->storage->runFile());
    }

    /**
     * URL of the progress page for the extraction in progress (with its token), or null.
     */
    public function progressUrl(): ?string
    {
        $state = $this->storage->readJson($this->storage->stateFile());

        return is_string($state['token'] ?? null) ? rtrim(url('/'), '/').'/?resume-update='.$state['token'] : null;
    }

    /**
     * Completes the update, also after a manual extraction (FTP, File Manager).
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

            // The package is no longer needed (it was downloaded or uploaded here); the content export is kept
            if ($package !== '' && dirname($package) === $this->storage->packagesDirectory()) {
                File::delete($package);
            }

            File::delete([$this->storage->updaterFile(), $this->storage->runFile()]);
            $log[] = 'backup: '.($run['backup'] ?? '-');
        }

        return $log;
    }

    /**
     * Texts for the progress page (admin language) and for the visitors' page (site language).
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
