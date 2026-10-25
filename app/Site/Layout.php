<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Profile;

/**
 * Dati comuni a tutte le pagine pubbliche (barra laterale, menu, lingue, footer), preparati per il tema.
 */
final readonly class Layout
{
    /**
     * @param  list<string>  $headlines
     * @param  array<string, string|int>  $photo  attributi di <img> (src, width, height) o vuoto
     * @param  list<array{key: string, title: string, url: string}>  $menu
     * @param  list<array{code: string, name: string, url: string, current: bool}>  $languages
     * @param  list<array{platform: string, icon: string, url: string, label: string}>  $socials
     * @param  array{href: string, text: string}|null  $email  valori codificati (vedi Cloak)
     * @param  array{href: string, text: string}|null  $phone  valori codificati (vedi Cloak)
     */
    public function __construct(
        public string $locale,
        public Profile $profile,
        public array $headlines,
        public array $photo,
        public ?string $photoAlt,
        public string $homeUrl,
        public array $menu,
        public array $languages,
        public array $socials,
        public ?string $cvUrl,
        public ?string $cvLanguageName,
        public ?array $email,
        public ?array $phone,
        public bool $printEnabled,
        public ?string $lastUpdated,
        public ?string $lastUpdatedMachine,
        public ?string $gdprText,
    ) {}
}
