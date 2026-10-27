<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\ExperienceKind;
use App\Enums\PageSectionKey;
use App\Models\Article;
use App\Models\Experience;
use App\Models\Project;
use App\Site\Content\RichText;
use App\Site\Media\Images;
use App\Site\Seo\StructuredData;
use App\Support\Settings\Settings;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Prepares the metadata (title, description, canonical, hreflang, Open Graph, JSON-LD) of the public pages.
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
        private readonly Blog $blog,
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
            title: ($project->t('seo_title', $locale) ?? $title).' - '.$this->cv->profile()->full_name,
            description: $project->t('seo_description', $locale)
                ?? $this->excerpt($project->t('summary', $locale) ?? $project->t('details', $locale)),
            canonical: url((string) $this->urls->projectPath($project, $locale)),
            alternates: $this->urls->alternates(fn (string $code): ?string => $this->urls->projectPath($project, $code)),
            ogType: 'article',
            image: $this->images->url($project->image) ?? $this->defaultImage(),
            imageAlt: $project->t('image_alt', $locale) ?? $title,
            breadcrumbs: $this->breadcrumbs($locale, PageSectionKey::Projects, $title),
            section: PageSectionKey::Projects->value,
        );

        $page->jsonLd = [$this->structuredData->detailGraph($page, $locale)];

        return $page;
    }

    public function experience(Experience $experience, string $locale): Page
    {
        $role = (string) $experience->t('role', $locale);
        $heading = $experience->organization !== null ? $role.' - '.$experience->organization->name : $role;
        $section = $experience->kind === ExperienceKind::Volunteering ? PageSectionKey::Volunteering : PageSectionKey::Experience;

        $page = new Page(
            title: ($experience->t('seo_title', $locale) ?? $heading).' - '.$this->cv->profile()->full_name,
            description: $experience->t('seo_description', $locale)
                ?? $this->excerpt($experience->t('summary', $locale) ?? $experience->t('details', $locale)),
            canonical: url((string) $this->urls->experiencePath($experience, $locale)),
            alternates: $this->urls->alternates(fn (string $code): ?string => $this->urls->experiencePath($experience, $code)),
            ogType: 'article',
            image: $this->images->url($experience->organization?->logo) ?? $this->defaultImage(),
            imageAlt: $experience->organization->name ?? $role,
            breadcrumbs: $this->breadcrumbs($locale, $section, $role),
            section: $section->value,
        );

        $page->jsonLd = [$this->structuredData->detailGraph($page, $locale)];

        return $page;
    }

    /**
     * @param  LengthAwarePaginator<int, Article>  $articles
     */
    public function blog(string $locale, LengthAwarePaginator $articles): Page
    {
        $name = $this->cv->profile()->full_name;
        $title = (string) __('site.sections.blog');
        $pageNumber = $articles->currentPage();
        $path = $this->urls->blogPath($locale);

        $page = new Page(
            title: $title.($pageNumber > 1 ? ' - '.__('site.blog.page', ['page' => $pageNumber]) : '').' - '.$name,
            description: (string) __('site.blog.description', ['name' => $name]),
            canonical: url($path).($pageNumber > 1 ? '?page='.$pageNumber : ''),
            // Pages after the first one have no versions in other languages
            alternates: $pageNumber > 1 ? [] : $this->urls->alternates(fn (string $code): ?string => $this->blog->hasArticles($code) ? $this->urls->blogPath($code) : null),
            image: $this->defaultImage(),
            imageAlt: $name,
            breadcrumbs: [
                ['label' => $name ?: (string) __('site.actions.back_to_cv'), 'url' => url($this->urls->homePath($locale))],
                ['label' => $title, 'url' => null],
            ],
            section: 'blog',
        );

        $page->jsonLd = [$this->structuredData->blogGraph($page, $locale, $articles->items())];

        return $page;
    }

    public function article(Article $article, string $locale): Page
    {
        $name = $this->cv->profile()->full_name;
        $title = (string) $article->t('title', $locale);

        $page = new Page(
            title: $title.' - '.$name,
            description: $article->t('excerpt', $locale) ?? $this->excerpt($article->t('body', $locale)),
            canonical: url((string) $this->urls->articlePath($article, $locale)),
            alternates: $this->urls->alternates(fn (string $code): ?string => $this->urls->articlePath($article, $code)),
            ogType: 'article',
            image: $this->images->url($article->cover_image) ?? $this->defaultImage(),
            imageAlt: $article->t('cover_alt', $locale) ?? $title,
            breadcrumbs: [
                ['label' => $name ?: (string) __('site.actions.back_to_cv'), 'url' => url($this->urls->homePath($locale))],
                ['label' => (string) __('site.sections.blog'), 'url' => url($this->urls->blogPath($locale))],
                ['label' => $title, 'url' => null],
            ],
            section: 'blog',
        );

        $page->jsonLd = [$this->structuredData->articleGraph($page, $article, $locale)];

        return $page;
    }

    public function privacy(string $locale): Page
    {
        $name = $this->cv->profile()->full_name;
        $title = (string) __('site.privacy.title');

        return new Page(
            title: $title.' - '.$name,
            canonical: url($this->urls->privacyPath($locale)),
            alternates: $this->urls->alternates(fn (string $code): string => $this->urls->privacyPath($code)),
            imageAlt: $name,
            breadcrumbs: [
                ['label' => $name ?: (string) __('site.actions.back_to_cv'), 'url' => url($this->urls->homePath($locale))],
                ['label' => $title, 'url' => null],
            ],
            // Same text on every site built with Resume App: not needed in search results
            robots: 'noindex, follow',
            section: 'privacy',
        );
    }

    public function maintenance(): Page
    {
        return new Page(
            title: __('site.errors.maintenance_title').' - '.$this->cv->profile()->full_name,
            robots: 'noindex',
        );
    }

    public function notFound(): Page
    {
        return new Page(
            title: __('site.errors.not_found_title').' - '.$this->cv->profile()->full_name,
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

        return $headline !== null ? $profile->full_name.' - '.$headline : $profile->full_name;
    }

    /**
     * Image for sharing: the one from the SEO settings, otherwise the profile photo.
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
