<?php

declare(strict_types=1);

namespace App\Themes;

use InvalidArgumentException;

/**
 * Un tema installato, descritto dal suo theme.json.
 */
final readonly class Theme
{
    /**
     * @param  list<string>  $entrypoints  chiavi del manifest Vite da includere nelle pagine
     * @param  string|null  $sprite  chiave del manifest Vite dello sprite SVG delle icone
     */
    public function __construct(
        public string $name,
        public string $title,
        public string $version,
        public ?string $extends,
        public string $path,
        public array $entrypoints,
        public ?string $sprite = null,
    ) {}

    public static function fromDirectory(string $path): self
    {
        $manifest = $path.DIRECTORY_SEPARATOR.'theme.json';

        if (! is_file($manifest)) {
            throw new InvalidArgumentException("Theme manifest not found: {$manifest}");
        }

        /** @var array{name?: string, title?: string, version?: string, extends?: string|null, entrypoints?: list<string>, sprite?: string|null} $data */
        $data = json_decode((string) file_get_contents($manifest), true, flags: JSON_THROW_ON_ERROR);

        $name = $data['name'] ?? basename($path);

        return new self(
            name: $name,
            title: $data['title'] ?? $name,
            version: $data['version'] ?? '0.0.0',
            extends: $data['extends'] ?? null,
            path: $path,
            entrypoints: $data['entrypoints'] ?? [],
            sprite: $data['sprite'] ?? null,
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
     * Cartella degli asset compilati, relativa alla webroot (usata da Vite).
     */
    public function buildDirectory(): string
    {
        return 'themes/'.$this->name;
    }

    /**
     * Mappa icone semantiche → classi/identificativi del set di icone del tema.
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
