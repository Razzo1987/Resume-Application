<?php

declare(strict_types=1);

namespace App\Themes;

use InvalidArgumentException;

/**
 * An installed theme, described by its theme.json.
 */
final readonly class Theme
{
    /**
     * @param  list<string>  $entrypoints  Vite manifest keys to include in the pages
     * @param  string|null  $sprite  Vite manifest key of the SVG icon sprite
     * @param  string|null  $themeColor  color of the browser bar on mobile (meta theme-color)
     * @param  string|null  $requires  compatible app versions (e.g. "^2.0")
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $version,
        public ?string $extends,
        public string $path,
        public array $entrypoints,
        public ?string $sprite = null,
        public ?string $themeColor = null,
        public ?string $requires = null,
        public ?string $description = null,
    ) {}

    public static function fromDirectory(string $path): self
    {
        $manifest = $path.DIRECTORY_SEPARATOR.'theme.json';

        if (! is_file($manifest)) {
            throw new InvalidArgumentException("Theme manifest not found: {$manifest}");
        }

        return self::fromArray(
            json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR),
            $path,
        );
    }

    /**
     * @param  mixed  $data  contents of theme.json
     */
    public static function fromArray(mixed $data, string $path): self
    {
        if (! is_array($data)) {
            throw new InvalidArgumentException('Invalid theme manifest');
        }

        $string = fn (string $key): ?string => isset($data[$key]) && is_string($data[$key]) && $data[$key] !== '' ? $data[$key] : null;
        $name = $string('name') ?? basename($path);
        $entrypoints = array_values(array_filter((array) ($data['entrypoints'] ?? []), 'is_string'));
        $requires = is_array($data['requires'] ?? null) && is_string($data['requires']['resume-app'] ?? null)
            ? $data['requires']['resume-app']
            : null;

        return new self(
            name: $name,
            title: $string('title') ?? $name,
            version: $string('version') ?? '0.0.0',
            extends: $string('extends'),
            path: $path,
            entrypoints: $entrypoints,
            sprite: $string('sprite'),
            themeColor: preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $string('theme_color')) === 1 ? $string('theme_color') : null,
            requires: $requires,
            description: $string('description'),
        );
    }

    public function viewsPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'views';
    }

    public function langPath(): string
    {
        return $this->path.DIRECTORY_SEPARATOR.'lang';
    }

    /**
     * Are the compiled assets present (Vite manifest in the webroot)?
     */
    public function hasAssets(string $publicPath): bool
    {
        return is_file($publicPath.DIRECTORY_SEPARATOR.$this->buildDirectory().DIRECTORY_SEPARATOR.'manifest.json');
    }

    /**
     * Folder of the compiled assets, relative to the webroot (used by Vite).
     */
    public function buildDirectory(): string
    {
        return 'themes/'.$this->name;
    }

    /**
     * Map of semantic icons → classes/identifiers of the theme's icon set.
     *
     * @return array<string, string>
     */
    public function icons(): array
    {
        $file = $this->path.DIRECTORY_SEPARATOR.'icons.php';

        if (! is_file($file)) {
            return [];
        }

        /** @var array<string, string> $icons */
        $icons = require $file;

        return $icons;
    }
}
