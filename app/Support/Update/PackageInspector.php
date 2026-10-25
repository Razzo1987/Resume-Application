<?php

declare(strict_types=1);

namespace App\Support\Update;

use App\Support\System\Updater;
use App\Themes\Compatibility;
use App\Themes\ThemeManager;
use Illuminate\Support\Number;
use Throwable;
use ZipArchive;

/**
 * Controlli su un pacchetto prima di aggiornare: che sia davvero Resume App, più recente, compatibile con
 * questo server e con i temi installati, e che i file si possano scrivere. Le regole sui nomi dei file e sulle
 * cartelle protette sono quelle dello script di estrazione (resources/update/updater.php).
 */
class PackageInspector
{
    /** File che ogni pacchetto deve contenere */
    public const array REQUIRED = ['index.php', 'core/VERSION', 'core/manifest.json', 'core/artisan', 'core/bootstrap/app.php', 'core/vendor/autoload.php'];

    /**
     * @param  string|null  $core  cartella dell'app (di default base_path(); serve ai test)
     * @param  string|null  $webroot  webroot (di default public_path())
     */
    public function __construct(
        private readonly Updater $updater,
        private readonly ThemeManager $themes,
        private ?string $core = null,
        private ?string $webroot = null,
    ) {
        require_once resource_path('update/updater.php');
    }

    public function core(): string
    {
        return rtrim($this->core ?? base_path(), '/');
    }

    public function webroot(): string
    {
        return rtrim($this->webroot ?? public_path(), '/');
    }

    /**
     * Installato da pacchetto (core/manifest.json): una copia di sviluppo non si aggiorna da qui.
     */
    public function isPackagedInstall(): bool
    {
        return is_file($this->core().'/manifest.json');
    }

    public function inspect(string $file): PackageReport
    {
        $current = $this->updater->codeVersion();
        $problems = [];
        $notes = [];
        $version = null;
        $entries = 0;
        $size = 0;

        $zip = new ZipArchive;

        if (! is_file($file) || $zip->open($file, ZipArchive::RDONLY) !== true) {
            return new PackageReport($file, null, $current, 0, 0, [__('admin.updates.problems.not_zip')], []);
        }

        try {
            $entries = $zip->numFiles;
            $names = [];

            for ($index = 0; $index < $entries; $index++) {
                $stat = $zip->statIndex($index);
                $name = (string) ($stat['name'] ?? '');
                $names[$name] = true;
                $size += (int) ($stat['size'] ?? 0);

                if (! resume_updater_safe_name($name)) {
                    $problems[] = __('admin.updates.problems.unsafe_name', ['file' => $name]);
                    break;
                }
            }

            $missing = array_values(array_filter(self::REQUIRED, fn (string $required): bool => ! isset($names[$required])));

            if ($missing !== []) {
                $problems[] = __('admin.updates.problems.not_package', ['files' => implode(', ', $missing)]);

                return new PackageReport($file, null, $current, $entries, $size, $problems, $notes);
            }

            $version = trim((string) $zip->getFromName('core/VERSION'));
            /** @var array{name?: string, version?: string, requires?: array{php?: string, extensions?: list<string>}} $manifest */
            $manifest = json_decode((string) $zip->getFromName('core/manifest.json'), true) ?: [];

            if (($manifest['name'] ?? null) !== 'resume-app' || ($manifest['version'] ?? null) !== $version) {
                $problems[] = __('admin.updates.problems.not_package', ['files' => 'core/manifest.json']);
            }

            $problems = [...$problems, ...$this->checkVersion($version, $current, $notes)];
            $problems = [...$problems, ...$this->checkServer($manifest['requires'] ?? [], $size)];
            $problems = [...$problems, ...$this->checkThemes($version)];
            $problems = [...$problems, ...$this->checkWritable(array_keys($names))];
        } catch (Throwable $exception) {
            $problems[] = $exception->getMessage();
        } finally {
            $zip->close();
        }

        if (! $this->isPackagedInstall()) {
            $problems[] = __('admin.updates.problems.not_packaged');
        }

        return new PackageReport($file, $version, $current, $entries, $size, array_values(array_unique($problems)), $notes);
    }

    /**
     * @param  list<string>  $notes
     * @return list<string>
     */
    private function checkVersion(string $version, string $current, array &$notes): array
    {
        if (preg_match('/^\d+\.\d+\.\d+(-[0-9A-Za-z.]+)?$/', $version) !== 1) {
            return [__('admin.updates.problems.bad_version', ['version' => $version])];
        }

        if (preg_match('/^\d+\.\d+\.\d+/', $current) === 1) {
            $compare = version_compare($version, $current);

            if ($compare < 0) {
                return [__('admin.updates.problems.older', ['version' => $version, 'current' => $current])];
            }

            if ($compare === 0) {
                $notes[] = (string) __('admin.updates.notes.same_version');
            }
        }

        return [];
    }

    /**
     * @param  array{php?: string, extensions?: list<string>}  $requires
     * @return list<string>
     */
    private function checkServer(array $requires, int $size): array
    {
        $problems = [];

        if (isset($requires['php']) && version_compare(PHP_VERSION, $requires['php'], '<')) {
            $problems[] = __('admin.updates.problems.php', ['required' => $requires['php'], 'current' => PHP_VERSION]);
        }

        foreach ($requires['extensions'] ?? [] as $extension) {
            if (! extension_loaded($extension)) {
                $problems[] = __('admin.updates.problems.extension', ['extension' => $extension]);
            }
        }

        $free = @disk_free_space($this->core());

        // I file vengono sostituiti uno alla volta: basta lo spazio del pacchetto estratto, con un margine
        if ($free !== false && $free < $size * 1.2) {
            $problems[] = __('admin.updates.problems.disk', ['required' => Number::fileSize((int) ($size * 1.2), 1), 'free' => Number::fileSize((int) $free, 1)]);
        }

        return $problems;
    }

    /**
     * I temi installati devono dichiararsi compatibili con la nuova versione.
     *
     * @return list<string>
     */
    private function checkThemes(string $version): array
    {
        $problems = [];

        foreach (array_keys($this->themes->installed()) as $name) {
            $theme = $this->themes->get($name);

            if ($name !== config('resume.themes.base') && $theme->requires !== null && ! Compatibility::satisfies($version, $theme->requires)) {
                $problems[] = __('admin.updates.problems.theme', ['theme' => $theme->title, 'requires' => $theme->requires, 'version' => $version]);
            }
        }

        return $problems;
    }

    /**
     * Le cartelle che riceveranno file (o la prima cartella esistente sopra quelle nuove) devono essere scrivibili.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    private function checkWritable(array $names): array
    {
        $checked = [];

        foreach ($names as $name) {
            $target = resume_updater_target($name, $this->webroot(), $this->core());

            if ($target === null) {
                continue;
            }

            $directory = str_ends_with($name, '/') ? $target : dirname($target);

            while (! is_dir($directory) && dirname($directory) !== $directory) {
                $directory = dirname($directory);
            }

            if (isset($checked[$directory])) {
                continue;
            }

            $checked[$directory] = true;

            if (! is_writable($directory)) {
                return [__('admin.updates.problems.not_writable', ['path' => $directory])];
            }
        }

        return [];
    }
}
