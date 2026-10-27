<?php

declare(strict_types=1);

namespace App\Themes;

use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * Resolves the active theme and its inheritance chain (active theme → base theme).
 */
final class ThemeManager
{
    /** @var array<string, Theme> */
    private array $loaded = [];

    private string|false|null $spriteUrl = null;

    public function __construct(
        private readonly string $themesPath,
        private readonly string $activeName,
        private readonly string $baseName,
        private readonly ?string $publicPath = null,
    ) {}

    /**
     * Active theme; if it is missing (or its compiled assets are), the base theme is used so the site does not break.
     */
    public function active(): Theme
    {
        if ($this->activeName !== $this->baseName && $this->isInstalled($this->activeName)) {
            $theme = $this->get($this->activeName);

            if ($theme->hasAssets($this->publicPath())) {
                return $theme;
            }
        }

        return $this->get($this->baseName);
    }

    public function activeName(): string
    {
        return $this->activeName;
    }

    /**
     * Webroot that contains the compiled theme assets (themes/{theme}).
     */
    public function publicPath(): string
    {
        return $this->publicPath ?? public_path();
    }

    public function path(string $name): string
    {
        return $this->themesPath.DIRECTORY_SEPARATOR.$name;
    }

    public function base(): Theme
    {
        return $this->get($this->baseName);
    }

    public function get(string $name): Theme
    {
        return $this->loaded[$name] ??= Theme::fromDirectory($this->path($name));
    }

    /**
     * Forgets the themes already read (after an install or a removal).
     */
    public function forget(): void
    {
        $this->loaded = [];
        $this->spriteUrl = null;
    }

    public function isInstalled(string $name): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) === 1
            && is_file($this->themesPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'theme.json');
    }

    /**
     * Installed themes: name => title.
     *
     * @return array<string, string>
     */
    public function installed(): array
    {
        $themes = [];

        foreach (glob($this->themesPath.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $dir) {
            if ($this->isInstalled(basename($dir))) {
                $theme = $this->get(basename($dir));
                $themes[$theme->name] = $theme->title;
            }
        }

        ksort($themes);

        return $themes;
    }

    /**
     * Themes from the most specific to the most generic: [active, ..., base].
     *
     * @return list<Theme>
     */
    public function chain(): array
    {
        $chain = [];
        $theme = $this->active();
        $seen = [];

        while (! isset($seen[$theme->name])) {
            $seen[$theme->name] = true;
            $chain[] = $theme;

            $parent = $theme->extends ?? ($theme->name === $this->baseName ? null : $this->baseName);

            if ($parent === null || ! $this->isInstalled($parent)) {
                break;
            }

            $theme = $this->get($parent);
        }

        return $chain;
    }

    /**
     * View folders in order of priority.
     *
     * @return list<string>
     */
    public function viewPaths(): array
    {
        return array_values(array_filter(
            array_map(fn (Theme $theme): string => $theme->viewsPath(), $this->chain()),
            'is_dir',
        ));
    }

    /**
     * Icons of the active theme, falling back to those of the themes it inherits from.
     *
     * @return array<string, string>
     */
    public function icons(): array
    {
        $icons = [];

        foreach (array_reverse($this->chain()) as $theme) {
            $icons = array_replace($icons, $theme->icons());
        }

        return $icons;
    }

    public function icon(string $name): ?string
    {
        return $this->icons()[$name] ?? null;
    }

    /**
     * URL of the SVG icon sprite (the first theme in the chain that declares one), if compiled.
     */
    public function spriteUrl(): ?string
    {
        if ($this->spriteUrl === null) {
            $this->spriteUrl = false;

            foreach ($this->chain() as $theme) {
                if ($theme->sprite === null) {
                    continue;
                }

                try {
                    $this->spriteUrl = Vite::asset($theme->sprite, $theme->buildDirectory());
                } catch (Throwable) {
                    // Assets not compiled (npm run build): the icon font classes are used
                }

                break;
            }
        }

        return $this->spriteUrl === false ? null : $this->spriteUrl;
    }
}
