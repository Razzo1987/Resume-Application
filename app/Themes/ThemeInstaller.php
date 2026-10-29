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
 * Installs, updates and removes additional themes (e.g. PRO themes) from a zip package (one theme, or several themes
 * each in its own folder) created with scripts/build-theme.sh:
 *
 *   {name}/theme.json, icons.php, views/, lang/   → themes folder (core/themes/{name})
 *   {name}/dist/                                  → webroot/themes/{name} (compiled assets)
 *
 * The content is checked before any file is written: only the expected file types,
 * no path outside the folder, no PHP file among the public assets.
 */
class ThemeInstaller
{
    private const int MAX_FILES = 3000;

    private const int MAX_BYTES = 50 * 1024 * 1024;

    /** Allowed theme files (relative to the theme folder) */
    private const string THEME_FILES = '#^(theme\.json|icons\.php|README\.md|LICENSE(\.txt|\.md)?'
        .'|views/([a-z0-9_-]+/)*[a-z0-9_.-]+\.blade\.php'
        .'|lang/[A-Za-z0-9_-]+/[a-z0-9_-]+\.php|lang/[A-Za-z0-9_-]+\.json)$#';

    /** Allowed public assets */
    private const string DIST_FILES = '#^dist/([A-Za-z0-9_.@-]+/)*[A-Za-z0-9_.@-]+\.(css|js|mjs|map|json|svg|woff2?|ttf|otf|png|jpe?g|webp|avif|gif|ico|txt)$#';

    public function __construct(
        private readonly ThemeManager $themes,
        private readonly PageCache $pages,
        private readonly Updater $updater,
    ) {}

    /**
     * Reads and checks the package without installing it (the first theme of a package with several themes).
     */
    public function inspect(string $zipPath): Theme
    {
        return $this->inspectAll($zipPath)[0];
    }

    /**
     * Reads and checks the package without installing it: the themes it contains.
     *
     * @return non-empty-list<Theme>
     */
    public function inspectAll(string $zipPath): array
    {
        ['zip' => $zip, 'themes' => $themes] = $this->open($zipPath);
        $zip->close();

        return array_column($themes, 'theme');
    }

    /**
     * Installs a package with one theme.
     *
     * @return array{theme: Theme, updated: bool, previous: string|null}
     */
    public function install(string $zipPath): array
    {
        return $this->installAll($zipPath)[0];
    }

    /**
     * Installs or updates every theme of the package (one theme, or several themes in their own folders).
     *
     * @return non-empty-list<array{theme: Theme, updated: bool, previous: string|null}>
     */
    public function installAll(string $zipPath): array
    {
        ['zip' => $zip, 'themes' => $themes] = $this->open($zipPath);

        $stage = Str::lower(Str::random(8));
        $staged = [];

        // 1) Extraction into temporary folders next to the final ones (same disk: atomic rename)
        try {
            foreach ($themes as $item) {
                $name = $item['theme']->name;
                $themeDir = $this->themes->path($name);
                $publicDir = $this->themes->publicPath().DIRECTORY_SEPARATOR.'themes'.DIRECTORY_SEPARATOR.$name;
                $staged[$name] = [
                    'theme' => $themeDir,
                    'public' => $publicDir,
                    'new_theme' => "{$themeDir}.new-{$stage}",
                    'new_public' => "{$publicDir}.new-{$stage}",
                    'previous' => $this->themes->isInstalled($name) ? $this->themes->get($name)->version : null,
                ];

                foreach ($item['entries'] as $index => $relative) {
                    $target = str_starts_with($relative, 'dist/')
                        ? $staged[$name]['new_public'].'/'.substr($relative, 5)
                        : $staged[$name]['new_theme'].'/'.$relative;

                    File::ensureDirectoryExists(dirname($target));

                    $content = $zip->getFromIndex($index);

                    if ($content === false || file_put_contents($target, $content) === false) {
                        throw new ThemeException(__('admin.themes.errors.write', ['path' => $item['prefix'].$relative]));
                    }
                }
            }
        } catch (ThemeException $exception) {
            foreach ($staged as $paths) {
                File::deleteDirectory($paths['new_theme']);
                File::deleteDirectory($paths['new_public']);
            }

            throw $exception;
        } finally {
            $zip->close();
        }

        // 2) Replacement, theme by theme: first the assets, then views and configuration
        $results = [];

        foreach ($staged as $name => $paths) {
            $this->swap($paths['new_public'], $paths['public']);
            $this->swap($paths['new_theme'], $paths['theme']);
            $this->refresh($paths['theme']);

            $results[] = ['theme' => $this->themes->get($name), 'updated' => $paths['previous'] !== null, 'previous' => $paths['previous']];
        }

        return $results;
    }

    /**
     * Removes a theme that is not in use (the base theme is never removed).
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
     * Opens and checks the package. Accepted layouts:
     *   theme.json, views/, dist/...            one theme at the root
     *   {name}/theme.json, {name}/dist/...      one theme in its folder
     *   {a}/theme.json, {b}/theme.json, ...     several themes, each in its folder (parents first)
     *
     * @return array{zip: ZipArchive, themes: non-empty-list<array{theme: Theme, prefix: string, entries: array<int, string>}>}
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
            $bytes = 0;

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);

                if ($stat === false) {
                    throw new ThemeException(__('admin.themes.errors.not_zip'));
                }

                $names[$index] = $stat['name'];
                $bytes += (int) $stat['size'];
            }

            if ($bytes > self::MAX_BYTES) {
                throw new ThemeException(__('admin.themes.errors.too_big'));
            }

            // Theme folders: "" (theme.json at the root) or "{name}/" for each {name}/theme.json
            $prefixes = in_array('theme.json', $names, true)
                ? ['']
                : array_values(array_map(
                    fn (string $name): string => substr($name, 0, -strlen('theme.json')),
                    array_filter($names, fn (string $name): bool => str_ends_with($name, '/theme.json') && substr_count($name, '/') === 1),
                ));

            if ($prefixes === []) {
                throw new ThemeException(__('admin.themes.errors.not_theme'));
            }

            $themes = [];

            foreach ($prefixes as $prefix) {
                $themes[$prefix] = ['theme' => $this->manifest($zip, $prefix), 'prefix' => $prefix, 'entries' => []];
            }

            $inPackage = array_map(fn (array $item): string => $item['theme']->name, $themes);

            foreach ($themes as $item) {
                $this->validateTheme($item['theme'], $item['prefix'], $inPackage);
            }

            foreach ($names as $index => $name) {
                if (str_ends_with($name, '/')) {
                    continue; // folders
                }

                $prefix = collect($prefixes)->first(fn (string $prefix): bool => str_starts_with($name, $prefix));
                $relative = $prefix !== null ? substr($name, strlen($prefix)) : null;

                if ($prefix === null || $relative === null || str_contains($relative, '..') || str_contains($relative, '\\') || $this->isSymlink($zip, $index)
                    || (preg_match(self::THEME_FILES, $relative) !== 1 && preg_match(self::DIST_FILES, $relative) !== 1)) {
                    throw new ThemeException(__('admin.themes.errors.unexpected_file', ['file' => $name]));
                }

                $themes[$prefix]['entries'][$index] = $relative;
            }

            foreach ($themes as $prefix => $item) {
                if (! in_array('dist/manifest.json', $item['entries'], true)) {
                    throw new ThemeException(__('admin.themes.errors.no_assets'));
                }

                /** @var array<string, mixed> $manifest */
                $manifest = json_decode((string) $zip->getFromName($prefix.'dist/manifest.json'), true) ?: [];

                foreach ($item['theme']->allEntrypoints() as $entrypoint) {
                    if (! isset($manifest[$entrypoint])) {
                        throw new ThemeException(__('admin.themes.errors.no_assets'));
                    }
                }
            }
        } catch (ThemeException $exception) {
            $zip->close();

            throw $exception;
        }

        // Parents before the themes that extend them
        $ordered = array_values($themes);
        usort($ordered, fn (array $a, array $b): int => ($a['theme']->name === $b['theme']->extends ? -1 : 0) + ($b['theme']->name === $a['theme']->extends ? 1 : 0));

        return ['zip' => $zip, 'themes' => $ordered];
    }

    private function manifest(ZipArchive $zip, string $prefix): Theme
    {
        try {
            $data = json_decode((string) $zip->getFromName($prefix.'theme.json'), true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ThemeException(__('admin.themes.errors.manifest'));
        }

        if (! is_array($data) || ! is_string($data['name'] ?? null)) {
            throw new ThemeException(__('admin.themes.errors.manifest'));
        }

        return Theme::fromArray($data, $this->themes->path($data['name']));
    }

    /**
     * @param  array<array-key, string>  $inPackage  names of the themes in the same package
     */
    private function validateTheme(Theme $theme, string $prefix, array $inPackage = []): void
    {
        if (preg_match('/^[a-z0-9][a-z0-9-]*$/', $theme->name) !== 1 || ($prefix !== '' && $prefix !== $theme->name.'/')) {
            throw new ThemeException(__('admin.themes.errors.manifest'));
        }

        if ($theme->name === $this->themes->base()->name) {
            throw new ThemeException(__('admin.themes.errors.base'));
        }

        if ($theme->extends !== null && ! $this->themes->isInstalled($theme->extends) && ! in_array($theme->extends, $inPackage, true)) {
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
     * Puts the new folder in place of the existing one (the old one is deleted).
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

        // icons.php and the theme language files are PHP files: OPcache could keep the previous version
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
