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
 * Checks on a package before updating: that it really is Resume App, newer, compatible with this server and with
 * the installed themes, and that the files can be written. The rules on file names and protected folders are the
 * ones of the extraction script (resources/update/updater.php).
 */
class PackageInspector
{
    /** Files that every package must contain */
    public const array REQUIRED = ['index.php', 'core/VERSION', 'core/manifest.json', 'core/artisan', 'core/bootstrap/app.php', 'core/vendor/autoload.php'];

    /**
     * @param  string|null  $core  the app folder (base_path() by default; used by the tests)
     * @param  string|null  $webroot  web root (public_path() by default)
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
     * Installed from a package (core/manifest.json): a development copy is not updated from here.
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

        // Files are replaced one at a time: the space of the extracted package is enough, with a margin
        if ($free !== false && $free < $size * 1.2) {
            $problems[] = __('admin.updates.problems.disk', ['required' => Number::fileSize((int) ($size * 1.2), 1), 'free' => Number::fileSize((int) $free, 1)]);
        }

        return $problems;
    }

    /**
     * The installed themes must declare themselves compatible with the new version.
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
     * The folders that will receive files (or the first existing folder above the new ones) must be writable.
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
