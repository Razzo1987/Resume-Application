<?php

declare(strict_types=1);

namespace App\Themes;

/**
 * A variant of a theme (palette and fonts), declared in theme.json under "variants":
 *
 *   "variants": {
 *       "lux": { "title": "Lux", "entrypoints": ["themes/folio/resources/scss/lux.scss"], "theme_color": "#1a1a1a" }
 *   }
 *
 * Its entry points are added to those of the theme; the administrator picks the variant on the Themes page.
 */
final readonly class ThemeVariant
{
    /**
     * @param  list<string>  $entrypoints  Vite manifest keys added to those of the theme
     */
    public function __construct(
        public string $name,
        public string $title,
        public array $entrypoints,
        public ?string $themeColor = null,
        public ?string $description = null,
    ) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(string $name, array $data): self
    {
        $string = fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;

        return new self(
            name: $name,
            title: $string('title') ?? $name,
            entrypoints: array_values(array_filter((array) ($data['entrypoints'] ?? []), 'is_string')),
            themeColor: Theme::color($string('theme_color')),
            description: $string('description'),
        );
    }
}
