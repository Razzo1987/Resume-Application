<?php

declare(strict_types=1);

namespace App\Themes;

use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * Risolve il tema attivo e la sua catena di ereditarietà (tema attivo → tema base).
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
    ) {}

    public function active(): Theme
    {
        return $this->get($this->isInstalled($this->activeName) ? $this->activeName : $this->baseName);
    }

    public function base(): Theme
    {
        return $this->get($this->baseName);
    }

    public function get(string $name): Theme
    {
        return $this->loaded[$name] ??= Theme::fromDirectory($this->themesPath.DIRECTORY_SEPARATOR.$name);
    }

    public function isInstalled(string $name): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $name) === 1
            && is_file($this->themesPath.DIRECTORY_SEPARATOR.$name.DIRECTORY_SEPARATOR.'theme.json');
    }

    /**
     * Temi installati: nome => titolo.
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
     * Temi dal più specifico al più generico: [attivo, ..., base].
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
     * Cartelle delle view in ordine di priorità.
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
     * Icone del tema attivo, con fallback su quelle dei temi da cui eredita.
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
     * URL dello sprite SVG delle icone (il primo tema della catena che ne dichiara uno), se compilato.
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
                    // Asset non compilati (npm run build): si usano le classi del font di icone
                }

                break;
            }
        }

        return $this->spriteUrl === false ? null : $this->spriteUrl;
    }
}
