<?php

declare(strict_types=1);

namespace App\Support\System;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * File rimasti da versioni precedenti: presenti nelle cartelle gestite dal pacchetto ma non nel suo manifest.
 * Funziona solo con il pacchetto di rilascio (core/manifest.json). Non tocca mai dati, .env, caricamenti e temi PRO.
 */
class OrphanFiles
{
    /** Cartelle che appartengono al pacchetto, relative alla webroot */
    public const array MANAGED = [
        'core/app', 'core/bootstrap/app.php', 'core/bootstrap/providers.php', 'core/config', 'core/database',
        'core/lang', 'core/resources', 'core/routes', 'core/stubs', 'core/themes/base', 'core/vendor',
        'themes/base', 'js/filament', 'css/filament', 'fonts/filament',
    ];

    /**
     * @param  string|null  $core  cartella core/ del pacchetto (di default quella dell'app)
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
     * @return list<string> percorsi relativi alla webroot
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
            // Si cancella solo ciò che risulta davvero orfano in questo momento
            if (isset($orphans[$path]) && @unlink($this->root().'/'.$path)) {
                $deleted++;
                $this->removeEmptyParents($path);
            }
        }

        return $deleted;
    }

    /**
     * Toglie le cartelle rimaste vuote dopo la cancellazione, senza mai risalire oltre la cartella gestita.
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
     * Radice del pacchetto: la cartella che contiene core/ (cioè la webroot).
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
