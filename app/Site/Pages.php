<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\ExperienceKind;
use App\Enums\PageSectionKey;
use App\Models\Experience;
use App\Models\Project;
use App\Site\Content\RichText;
use App\Site\Media\Images;
use App\Site\Seo\StructuredData;
use App\Support\Settings\Settings;

/**
 * Prepara i metadati (titolo, descrizione, canonical, hreflang, Open Graph, JSON-LD) delle pagine pubbliche.
 */
final class Pages
{
    public function __construct(
        private readonly CvData $cv,
        private readonly SiteUrls $urls,
        private readonly Settings $settings,
        private readonly RichText $richText,
        private readonly Images $images,
        private readonly StructuredData $structuredData,
    ) {}

    public function cv(string $locale): Page
    {
        $profile = $this->cv->profile();

        $page = new Page(
            title: $this->settings->translated('seo.title', $locale) ?? $this->defaultTitle($locale),
            description: $this->settings->translated('seo.description', $locale) ?? $this->excerpt($profile->t('bio', $locale)),
            canonical: url($this->urls->homePath($locale)),
            alternates: $this->urls->alternates(fn (string $code): string => $this->urls->homePath($code)),
            ogType: 'profile',
            image: $this->defaultImage(),
            imageAlt: $profile->t('photo_alt', $locale) ?? $profile->full_name,
            isCv: true,
        );

        $page->jsonLd = [$this->structuredData->cvGraph($page, $locale)];

        return $page;
    }

    public function project(Project $project, string $locale): Page
    {
        $title = (string) $project->t('title', $locale);

        $page = new Page(
            title: ($project->t('seo_title', $locale) ?? $title).' — '.$this->cv->profile()->full_name,
            description: $project->t('seo_description', $locale)
                ?? $this->excerpt($project->t('summary', $locale) ?? $project->t('details', $locale)),
            canonical: url((string) $this->urls->projectPath($project, $locale)),
            alternates: $this->urls->alternates(fn (string $code): ?string => $this->urls->projectPath($project, $code)),
            ogType: 'article',
            image: $this->images->url($project->image) ?? $this->defaultImage(),
            imageAlt: $project->t('image_alt', $locale) ?? $title,
            breadcrumbs: $this->breadcrumbs($locale, PageSectionKey::Projects, $title),
        );

        $page->jsonLd = [$this->structuredData->detailGraph($page, $locale)];

        return $page;
    }

    public function experience(Experience $experience, string $locale): Page
    {
        $role = (string) $experience->t('role', $locale);
        $heading = $experience->organization !== null ? $role.' — '.$experience->organization->name : $role;
        $section = $experience->kind === ExperienceKind::Volunteering ? PageSectionKey::Volunteering : PageSectionKey::Experience;

        $page = new Page(
            title: ($experience->t('seo_title', $locale) ?? $heading).' — '.$this->cv->profile()->full_name,
            description: $experience->t('seo_description', $locale)
                ?? $this->excerpt($experience->t('summary', $locale) ?? $experience->t('details', $locale)),
            canonical: url((string) $this->urls->experiencePath($experience, $locale)),
            alternates: $this->urls->alternates(fn (string $code): ?string => $this->urls->experiencePath($experience, $code)),
            ogType: 'article',
            image: $this->images->url($experience->organization?->logo) ?? $this->defaultImage(),
            imageAlt: $experience->organization->name ?? $role,
            breadcrumbs: $this->breadcrumbs($locale, $section, $role),
        );

        $page->jsonLd = [$this->structuredData->detailGraph($page, $locale)];

        return $page;
    }

    public function notFound(): Page
    {
        return new Page(
            title: __('site.errors.not_found_title').' — '.$this->cv->profile()->full_name,
            robots: 'noindex',
        );
    }

    /**
     * @return list<array{label: string, url: string|null}>
     */
    private function breadcrumbs(string $locale, PageSectionKey $section, string $current): array
    {
        return [
            ['label' => $this->cv->profile()->full_name ?: __('site.actions.back_to_cv'), 'url' => url($this->urls->homePath($locale))],
            ['label' => $this->cv->sectionTitle($section), 'url' => url($this->urls->sectionPath($locale, $section->value))],
            ['label' => $current, 'url' => null],
        ];
    }

    private function defaultTitle(string $locale): string
    {
        $profile = $this->cv->profile();
        $headline = $profile->headlineList($locale)[0] ?? null;

        return $headline !== null ? $profile->full_name.' — '.$headline : $profile->full_name;
    }

    /**
     * Immagine per la condivisione: quella delle impostazioni SEO, altrimenti la foto del profilo.
     */
    private function defaultImage(): ?string
    {
        $configured = $this->settings->get('seo.og_image');

        return $this->images->url(is_string($configured) ? $configured : null) ?? $this->images->url($this->cv->profile()->photo);
    }

    private function excerpt(?string $html): ?string
    {
        $text = $this->richText->plain($html, 160);

        return $text !== '' ? $text : null;
    }
}
