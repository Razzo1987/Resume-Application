<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Metadata of a public page: title, description, canonical URL, language versions,
 * Open Graph and structured data. The controller prepares it, the theme layout uses it.
 */
final class Page
{
    /**
     * @param  array<string, string>  $alternates  language code → absolute URL (the first one is the default language)
     * @param  list<array<string, mixed>>  $jsonLd
     * @param  list<array{label: string, url: string|null}>  $breadcrumbs
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $canonical = null,
        public array $alternates = [],
        public string $ogType = 'website',
        public ?string $image = null,
        public ?string $imageAlt = null,
        public array $jsonLd = [],
        public array $breadcrumbs = [],
        public ?string $robots = null,
        public bool $isCv = false,
        public ?string $section = null,
    ) {}

    public function xDefault(): ?string
    {
        return $this->alternates === [] ? null : $this->alternates[array_key_first($this->alternates)];
    }

    /**
     * URL of the page in the given language, if it exists.
     */
    public function alternate(string $locale): ?string
    {
        return $this->alternates[$locale] ?? null;
    }
}
