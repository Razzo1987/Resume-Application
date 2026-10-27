<?php

declare(strict_types=1);

namespace App\Support\System;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Files left over from previous versions: present in the folders managed by the package but not in its manifest.
 * Works only with the release package (core/manifest.json). Never touches data, .env, uploads or PRO themes.
 */
class OrphanFiles
{
    /** Folders that belong to the package, relative to the web root */
    public const array MANAGED = [
        'core/app', 'core/bootstrap/app.php', 'core/bootstrap/providers.php', 'core/config', 'core/database',
        'core/lang', 'core/resources', 'core/routes', 'core/stubs', 'core/themes/base', 'core/vendor',
        'themes/base', 'js/filament', 'css/filament', 'fonts/filament',
    ];

    /**
     * @param  string|null  $core  the package's core/ folder (the app's by default)
     */
    public function __construct(private ?string $core = null) {}

    public function manifestPath(): string
    {
        return $this->core().'/manifest.json';
    }

    public function isAvailable(): bool
    {
        return is_file($this->manifestPath()) && basename($this->core()) === 'core';
    }

    /**
     * @return list<string> paths relative to the web root
     */
    public function find(): array
    {
        if (! $this->isAvailable()) {
            return [];
        }

        /** @var array{files?: array<string, string>} $manifest */
        $manifest = json_decode((string) file_get_contents($this->manifestPath()), true) ?: [];
        $known = $manifest['files'] ?? [];
        $root = $this->root();
        $orphans = [];

        foreach (self::MANAGED as $managed) {
            $path = $root.'/'.$managed;

            if (is_file($path)) {
                continue;
            }

            if (! is_dir($path)) {
                continue;
            }

            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS));

            /** @var SplFileInfo $file */
            foreach ($files as $file) {
                $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($root))), '/');

                if (! isset($known[$relative])) {
                    $orphans[] = $relative;
                }
            }
        }

        sort($orphans);

        return $orphans;
    }

    /**
     * @param  list<string>  $paths
     */
    public function delete(array $paths): int
    {
        $orphans = array_flip($this->find());
        $deleted = 0;

        foreach ($paths as $path) {
            // Only delete what is actually orphaned at this moment
            if (isset($orphans[$path]) && @unlink($this->root().'/'.$path)) {
                $deleted++;
                $this->removeEmptyParents($path);
            }
        }

        return $deleted;
    }

    /**
     * Removes the folders left empty after the deletion, never going above the managed folder.
     */
    private function removeEmptyParents(string $path): void
    {
        $managed = collect(self::MANAGED)->first(fn (string $m): bool => str_starts_with($path, $m.'/'));

        if ($managed === null) {
            return;
        }

        $dir = dirname($path);

        while ($dir !== $managed && str_starts_with($dir, $managed.'/')) {
            $absolute = $this->root().'/'.$dir;

            if (! is_dir($absolute) || (new FilesystemIterator($absolute))->valid() || ! @rmdir($absolute)) {
                return;
            }

            $dir = dirname($dir);
        }
    }

    /**
     * Package root: the folder that contains core/ (that is, the web root).
     */
    private function root(): string
    {
        return dirname($this->core());
    }

    private function core(): string
    {
        return rtrim($this->core ?? base_path(), '/');
    }
}
