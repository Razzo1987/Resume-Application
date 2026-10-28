<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\PageSectionKey;
use App\Models\Language;
use App\Models\PageSection;
use App\Models\SocialLink;
use App\Site\Contact\Cloak;
use App\Site\Formatting\Dates;
use App\Site\Media\Images;
use App\Site\Media\SiteIcon;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;

/**
 * Builds the shared layout data ($layout) only once per page.
 */
final class LayoutFactory
{
    /** @var array<int, Layout> */
    private array $made = [];

    public function __construct(
        private readonly CvData $cv,
        private readonly SiteContext $site,
        private readonly SiteUrls $urls,
        private readonly CvFiles $files,
        private readonly Settings $settings,
        private readonly Locales $locales,
        private readonly LastUpdated $lastUpdated,
        private readonly Cloak $cloak,
        private readonly Images $images,
        private readonly Dates $dates,
        private readonly Blog $blog,
        private readonly SiteIcon $icon,
        private readonly PrivacyPolicy $privacy,
    ) {}

    public function make(Page $page): Layout
    {
        return $this->made[spl_object_id($page)] ??= $this->build($page);
    }

    private function build(Page $page): Layout
    {
        $language = $this->site->language();
        $locale = $language->code;
        $profile = $this->cv->profile();
        $cvDocument = $this->files->for($language);
        $updated = $this->settings->bool('footer.show_last_updated') ? $this->lastUpdated->date() : null;
        $hasBlog = $this->blog->hasArticles($locale);

        $menu = $this->cv->menu()->map(fn (PageSection $section): array => $section->key === PageSectionKey::LatestArticles
            // The blog has its own page: the item always links there, even from the CV
            ? [
                'key' => 'blog',
                'title' => $section->displayTitle($locale),
                'url' => url($this->urls->blogPath($locale)),
                'current' => $page->section === 'blog',
            ]
            : [
                'key' => $section->key->value,
                'title' => $section->displayTitle($locale),
                // On the CV page the anchor is enough; from the other pages it goes back to the CV
                'url' => $page->isCv ? '#'.$section->key->value : url($this->urls->sectionPath($locale, $section->key->value)),
                'current' => $page->section === $section->key->value,
            ])->values()->all();

        return new Layout(
            locale: $locale,
            profile: $profile,
            headlines: $profile->headlineList($locale),
            photo: $this->images->attributes($profile->photo),
            photoAlt: $profile->t('photo_alt', $locale) ?? $profile->full_name,
            homeUrl: url($this->urls->homePath($locale)),
            menu: $menu,
            languages: $this->locales->activeLanguages()->map(fn (Language $item): array => [
                'code' => $item->code,
                'name' => $item->name,
                'url' => $page->alternate($item->code) ?? url($this->urls->homePath($item->code)),
                'current' => $item->code === $locale,
            ])->values()->all(),
            socials: $this->cv->socials()->map(fn (SocialLink $link): array => [
                'platform' => $link->platform->value,
                'icon' => $link->platform->icon(),
                'url' => $link->url,
                'label' => $link->label ?: $link->platform->siteLabel(),
            ])->values()->all(),
            cvUrl: $cvDocument !== null ? url($this->urls->cvDownloadPath($locale)) : null,
            cvLanguageName: $cvDocument?->language->name,
            email: $this->settings->bool('privacy.show_email') && filled($profile->email)
                ? ['href' => $this->cloak->encode('mailto:'.$profile->email), 'text' => $this->cloak->encode((string) $profile->email)]
                : null,
            phone: $this->settings->bool('privacy.show_phone') && filled($profile->phone)
                ? ['href' => $this->cloak->encode('tel:'.$this->cloak->telephone((string) $profile->phone)), 'text' => $this->cloak->encode((string) $profile->phone)]
                : null,
            printEnabled: $this->settings->bool('print.enabled'),
            lastUpdated: $updated !== null ? $this->dates->localDay($updated) : null,
            lastUpdatedMachine: $updated?->copy()->setTimezone($this->settings->timezone())->format('Y-m-d'),
            gdprText: $this->settings->translated('footer.gdpr_text', $locale),
            blogUrl: $hasBlog ? url($this->urls->blogPath($locale)) : null,
            feedUrl: $hasBlog ? url($this->urls->feedPath($locale)) : null,
            icons: $this->icon->urls(),
            privacyUrl: $this->privacy->isEnabled() ? url($this->urls->privacyPath($locale)) : null,
        );
    }
}
