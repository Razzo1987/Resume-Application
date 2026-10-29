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
     * @param  array<string, ThemeVariant>  $variants  palettes/fonts the administrator can choose from
     * @param  string|null  $variant  variant in use (set by withVariant(); null = none or not resolved)
     * @param  string|null  $updateRepository  GitHub repository of the releases ("owner/name"), for updates
     * @param  string  $updateTagPrefix  prefix of the release tags of this theme ("v", "folio-v")
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
        public array $variants = [],
        public ?string $defaultVariant = null,
        public ?string $variant = null,
        public ?string $updateRepository = null,
        public string $updateTagPrefix = 'v',
        public ?string $author = null,
    ) {}

    /**
     * A CSS color for meta theme-color ("#2c3e50"), or null.
     */
    public static function color(?string $value): ?string
    {
        return preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $value) === 1 ? $value : null;
    }

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

        $variants = [];

        foreach ((array) ($data['variants'] ?? []) as $variantName => $variant) {
            if (is_string($variantName) && preg_match('/^[a-z0-9][a-z0-9-]*$/', $variantName) === 1 && is_array($variant)) {
                $variants[$variantName] = ThemeVariant::fromArray($variantName, $variant);
            }
        }

        $default = $string('default_variant');
        $updates = is_array($data['updates'] ?? null) ? $data['updates'] : [];
        $repository = is_string($updates['repository'] ?? null) && preg_match('#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $updates['repository']) === 1
            ? $updates['repository']
            : null;
        $tagPrefix = is_string($updates['tag_prefix'] ?? null) && preg_match('/^[A-Za-z0-9_.-]*$/', $updates['tag_prefix']) === 1
            ? $updates['tag_prefix']
            : 'v';

        return new self(
            name: $name,
            title: $string('title') ?? $name,
            version: $string('version') ?? '0.0.0',
            extends: $string('extends'),
            path: $path,
            entrypoints: $entrypoints,
            sprite: $string('sprite'),
            themeColor: self::color($string('theme_color')),
            requires: $requires,
            description: $string('description'),
            variants: $variants,
            defaultVariant: $default !== null && isset($variants[$default]) ? $default : (array_key_first($variants) ?? null),
            updateRepository: $repository,
            updateTagPrefix: $tagPrefix,
            author: $string('author'),
        );
    }

    /**
     * The theme with the chosen variant (the default one if the name is unknown): its entry points are added to
     * those of the theme and its color replaces the theme's one. Themes without variants are returned unchanged.
     */
    public function withVariant(?string $name): self
    {
        $variant = $this->variants[(string) $name] ?? ($this->defaultVariant !== null ? $this->variants[$this->defaultVariant] : null);

        if ($variant === null) {
            return $this;
        }

        return new self(
            name: $this->name,
            title: $this->title,
            version: $this->version,
            extends: $this->extends,
            path: $this->path,
            entrypoints: array_values(array_unique([...$this->entrypoints, ...$variant->entrypoints])),
            sprite: $this->sprite,
            themeColor: $variant->themeColor ?? $this->themeColor,
            requires: $this->requires,
            description: $this->description,
            variants: $this->variants,
            defaultVariant: $this->defaultVariant,
            variant: $variant->name,
            updateRepository: $this->updateRepository,
            updateTagPrefix: $this->updateTagPrefix,
            author: $this->author,
        );
    }

    /**
     * Entry points of the theme and of all its variants (everything the compiled assets must contain).
     *
     * @return list<string>
     */
    public function allEntrypoints(): array
    {
        $entrypoints = $this->entrypoints;

        foreach ($this->variants as $variant) {
            array_push($entrypoints, ...$variant->entrypoints);
        }

        return array_values(array_unique($entrypoints));
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
