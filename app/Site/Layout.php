<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Profile;

/**
 * Data shared by all public pages (sidebar, menu, languages, footer), prepared for the theme.
 */
final readonly class Layout
{
    /**
     * @param  list<string>  $headlines
     * @param  array<string, string|int>  $photo  <img> attributes (src, width, height) or empty
     * @param  list<array{key: string, title: string, url: string, current: bool}>  $menu
     * @param  list<array{code: string, name: string, url: string, current: bool}>  $languages
     * @param  list<array{platform: string, icon: string, url: string, label: string}>  $socials
     * @param  array{href: string, text: string}|null  $email  encoded values (see Cloak)
     * @param  array{href: string, text: string}|null  $phone  encoded values (see Cloak)
     * @param  array<int, string>|null  $icons  site icon: size => URL
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
        public ?string $blogUrl = null,
        public ?string $feedUrl = null,
        public ?array $icons = null,
        public ?string $privacyUrl = null,
    ) {}
}
