<?php

declare(strict_types=1);

namespace App\Themes;

use App\Site\Cache\PageCache;
use App\Support\System\Updater;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use JsonException;
use ZipArchive;

/**
 * Installa, aggiorna e rimuove i temi aggiuntivi (es. temi PRO) da un pacchetto zip creato con
 * scripts/build-theme.sh:
 *
 *   {nome}/theme.json, icons.php, views/, lang/   → cartella dei temi (core/themes/{nome})
 *   {nome}/dist/                                  → webroot/themes/{nome} (asset compilati)
 *
 * Il contenuto viene controllato prima di scrivere qualsiasi file: solo i tipi di file attesi,
 * nessun percorso fuori dalla cartella, nessun file PHP tra gli asset pubblici.
 */
class ThemeInstaller
{
    private const int MAX_FILES = 3000;

    private const int MAX_BYTES = 50 * 1024 * 1024;

    /** File del tema ammessi (relativi alla cartella del tema) */
    private const string THEME_FILES = '#^(theme\.json|icons\.php|README\.md|LICENSE(\.txt|\.md)?'
        .'|views/([a-z0-9_-]+/)*[a-z0-9_.-]+\.blade\.php'
        .'|lang/[A-Za-z0-9_-]+/[a-z0-9_-]+\.php|lang/[A-Za-z0-9_-]+\.json)$#';

    /** Asset pubblici ammessi */
    private const string DIST_FILES = '#^dist/([A-Za-z0-9_.@-]+/)*[A-Za-z0-9_.@-]+\.(css|js|mjs|map|json|svg|woff2?|ttf|otf|png|jpe?g|webp|avif|gif|ico|txt)$#';

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly PageCache $pages,
        private readonly Updater $updater,
    ) {}

    /**
     * Legge e controlla il pacchetto senza installarlo.
     */
    public function inspect(string $zipPath): Theme
    {
        return $this->open($zipPath)['theme'];
    }

    /**
     * @return array{theme: Theme, updated: bool, previous: string|null}
     */
    public function install(string $zipPath): array
    {
        ['zip' => $zip, 'theme' => $theme, 'prefix' => $prefix, 'entries' => $entries] = $this->open($zipPath);

        $name = $theme->name;
        $themeDir = $this->themes->path($name);
        $publicDir = $this->themes->publicPath().DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$name;
        $previous = $this->themes->isInstalled($name) ? $this->themes->get($name)->version : null;

        // 1) Estrazione in cartelle temporanee accanto a quelle definitive (stesso disco: rename atomico)
        $stage = Str::lower(Str::random(8));
        $newTheme = "{$themeDir}.new-{$stage}";
        $newPublic = "{$publicDir}.new-{$stage}";

        try {
            foreach ($entries as $index => $relative) {
                $target = str_starts_with($relative, 'dist/')
                    ? $newPublic.'/'.substr($relative, 5)
                    : $newTheme.'/'.$relative;

                File::ensureDirectoryExists(dirname($target));

                $content = $zip->getFromIndex($index);

                if ($content === false || file_put_contents($target, $content) === false) {
                    throw new ThemeException(__('admin.themes.errors.write', ['path' => $prefix.$relative]));
                }
            }
        } catch (ThemeException $exception) {
            File::deleteDirectory($newTheme);
            File::deleteDirectory($newPublic);

            throw $exception;
        } finally {
            $zip->close();
        }

        // 2) Sostituzione: prima gli asset, poi view e configurazione
        $this->swap($newPublic, $publicDir);
        $this->swap($newTheme, $themeDir);

        $this->refresh($themeDir);

        return ['theme' => $this->themes->get($name), 'updated' => $previous !== null, 'previous' => $previous];
    }

    /**
     * Rimuove un tema che non è in uso (il tema base non si rimuove mai).
     */
    public function remove(string $name): void
    {
        if ($name === $this->themes->base()->name || ! $this->themes->isInstalled($name)) {
            throw new ThemeException(__('admin.themes.errors.not_removable'));
        }

        if ($this->themes->active()->name === $name || $this->themes->activeName() === $name) {
            throw new ThemeException(__('admin.themes.errors.active'));
        }

        File::deleteDirectory($this->themes->publicPath().DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$name);
        File::deleteDirectory($this->themes->path($name));

        $this->refresh(null);
    }

    /**
     * @return array{zip: ZipArchive, theme: Theme, prefix: string, entries: array<int, string>}
     */
    private function open(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new ThemeException(__('admin.themes.errors.not_zip'));
        }

        try {
            if ($zip->numFiles === 0 || $zip->numFiles > self::MAX_FILES) {
                throw new ThemeException(__('admin.themes.errors.not_theme'));
            }

            $names = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new ThemeException(__('admin.themes.errors.not_zip'));
                }

                $names[$index] = $stat['name'];
            }

            // Cartella principale facoltativa ("mio-tema/theme.json" oppure "theme.json")
            $prefix = in_array('theme.json', $names, true) ? '' : (string) Str::before((string) collect($names)->first(fn (string $name): bool => str_ends_with($name, '/theme.json') && substr_count($name, '/') === 1), 'theme.json');

            if (! in_array($prefix.'theme.json', $names, true)) {
                throw new ThemeException(__('admin.themes.errors.not_theme'));
            }

            try {
                $data = json_decode((string) $zip->getFromName($prefix.'theme.json'), true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new ThemeException(__('admin.themes.errors.manifest'));
            }

            if (! is_array($data) || ! is_string($data['name'] ?? null)) {
                throw new ThemeException(__('admin.themes.errors.manifest'));
            }

            $theme = Theme::fromArray($data, $this->themes->path($data['name']));
            $this->validateTheme($theme, $prefix);

            $entries = [];
            $bytes = 0;
            $hasManifest = false;

            foreach ($names as $index => $name) {
                if (str_ends_with($name, '/')) {
                    continue; // cartelle
                }

                $relative = str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : null;

                if ($relative === null || str_contains($relative, '..') || str_contains($relative, '\\') || $this->isSymlink($zip, $index)
                    || (preg_match(self::THEME_FILES, $relative) !== 1 && preg_match(self::DIST_FILES, $relative) !== 1)) {
                    throw new ThemeException(__('admin.themes.errors.unexpected_file', ['file' => $name]));
                }

                $bytes += (int) ($zip->statIndex($index)['size'] ?? 0);
                $hasManifest = $hasManifest || $relative === 'dist/manifest.json';
                $entries[$index] = $relative;
            }

            if ($bytes > self::MAX_BYTES) {
                throw new ThemeException(__('admin.themes.errors.too_big'));
            }

            if (! $hasManifest) {
                throw new ThemeException(__('admin.themes.errors.no_assets'));
            }

            /** @var array<string, mixed> $manifest */
            $manifest = json_decode((string) $zip->getFromName($prefix.'dist/manifest.json'), true) ?: [];

            foreach ($theme->entrypoints as $entrypoint) {
                if (! isset($manifest[$entrypoint])) {
                    throw new ThemeException(__('admin.themes.errors.no_assets'));
                }
            }
        } catch (ThemeException $exception) {
            $zip->close();

            throw $exception;
        }

        return ['zip' => $zip, 'theme' => $theme, 'prefix' => $prefix, 'entries' => $entries];
    }

    private function validateTheme(Theme $theme, string $prefix): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $theme->name) !== 1 || ($prefix !== '' && $prefix !== $theme->name.'/')) {
            throw new ThemeException(__('admin.themes.errors.manifest'));
        }

        if ($theme->name === $this->themes->base()->name) {
            throw new ThemeException(__('admin.themes.errors.base'));
        }

        if ($theme->extends !== null && ! $this->themes->isInstalled($theme->extends)) {
            throw new ThemeException(__('admin.themes.errors.parent', ['theme' => $theme->extends]));
        }

        if ($theme->requires !== null && ! Compatibility::satisfies($this->updater->codeVersion(), $theme->requires)) {
            throw new ThemeException(__('admin.themes.errors.incompatible', ['requires' => $theme->requires, 'version' => $this->updater->codeVersion()]));
        }
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        $opsys = 0;
        $attributes = 0;

        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        return $opsys === ZipArchive::OPSYS_UNIX && (($attributes >> 16) & 0170000) === 0120000;
    }

    /**
     * Mette la nuova cartella al posto di quella esistente (la vecchia viene eliminata).
     */
    private function swap(string $new, string $target): void
    {
        if (! is_dir($new)) {
            File::deleteDirectory($target);

            return;
        }

        $old = null;

        if (is_dir($target)) {
            $old = $target.'.old-'.Str::lower(Str::random(8));
            rename($target, $old);
        }

        File::ensureDirectoryExists(dirname($target));

        if (! @rename($new, $target)) {
            File::copyDirectory($new, $target);
            File::deleteDirectory($new);
        }

        if ($old !== null) {
            File::deleteDirectory($old);
        }
    }

    private function refresh(?string $themeDir): void
    {
        $this->themes->forget();

        // icons.php e le lingue del tema sono file PHP: OPcache potrebbe tenere la versione precedente
        if ($themeDir !== null && function_exists('opcache_invalidate')) {
            foreach (File::allFiles($themeDir) as $file) {
                if ($file->getExtension() === 'php') {
                    @opcache_invalidate($file->getPathname(), true);
                }
            }
        }

        Artisan::call('view:clear');
        $this->pages->flush();
    }
}
