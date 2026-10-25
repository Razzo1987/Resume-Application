<?php

declare(strict_types=1);

namespace App\Site;

/**
 * Metadati di una pagina pubblica: titolo, descrizione, URL canonico, versioni linguistiche,
 * Open Graph e dati strutturati. Lo prepara il controller, lo usa il layout del tema.
 */
final class Page
{
    /**
     * @param  array<string, string>  $alternates  codice lingua → URL assoluto (la prima è la lingua di default)
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
     * URL della pagina nella lingua indicata, se esiste.
     */
    public function alternate(string $locale): ?string
    {
        return $this->alternates[$locale] ?? null;
    }
}
