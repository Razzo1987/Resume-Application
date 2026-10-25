<?php

declare(strict_types=1);

namespace App\Site\Seo;

use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\LanguageSkill;
use App\Models\Organization;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Site\Content\RichText;
use App\Site\CvData;
use App\Site\LastUpdated;
use App\Site\Media\Images;
use App\Site\Page;
use App\Site\SiteUrls;
use App\Support\Languages\Locales;
use App\Support\Settings\Settings;

/**
 * Dati strutturati Schema.org (JSON-LD).
 * Email, telefono e data di nascita sono esclusi a meno che l'admin non li attivi (D7).
 */
final class StructuredData
{
    public function __construct(
        private readonly CvData $cv,
        private readonly SiteUrls $urls,
        private readonly Settings $settings,
        private readonly RichText $richText,
        private readonly Images $images,
        private readonly Locales $locales,
        private readonly LastUpdated $lastUpdated,
    ) {}

    public function personId(): string
    {
        return $this->root().'#person';
    }

    public function websiteId(): string
    {
        return $this->root().'#website';
    }

    /**
     * Indirizzo della radice del sito, con lo slash finale ("https://example.com/").
     */
    private function root(): string
    {
        return rtrim(url('/'), '/').'/';
    }

    /**
     * Pagina CV: ProfilePage con la persona completa e le pubblicazioni.
     *
     * @return array<string, mixed>
     */
    public function cvGraph(Page $page, string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->website(),
                self::clean([
                    '@type' => 'ProfilePage',
                    '@id' => $page->canonical.'#page',
                    'url' => $page->canonical,
                    'name' => $page->title,
                    'inLanguage' => $locale,
                    'isPartOf' => ['@id' => $this->websiteId()],
                    'mainEntity' => ['@id' => $this->personId()],
                    'dateModified' => $this->lastUpdated->date()?->toAtomString(),
                ]),
                $this->person($locale),
                ...$this->publications($locale),
            ],
        ];
    }

    /**
     * Pagina di dettaglio (progetto, esperienza): WebPage con le briciole di pane.
     *
     * @return array<string, mixed>
     */
    public function detailGraph(Page $page, string $locale): array
    {
        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->website(),
                self::clean([
                    '@type' => 'WebPage',
                    '@id' => $page->canonical.'#page',
                    'url' => $page->canonical,
                    'name' => $page->title,
                    'description' => $page->description,
                    'inLanguage' => $locale,
                    'isPartOf' => ['@id' => $this->websiteId()],
                    'about' => ['@id' => $this->personId()],
                    'primaryImageOfPage' => $page->image,
                    'breadcrumb' => ['@id' => $page->canonical.'#breadcrumb'],
                ]),
                $this->breadcrumbList($page),
                self::clean([
                    '@type' => 'Person',
                    '@id' => $this->personId(),
                    'name' => $this->cv->profile()->full_name,
                    'url' => url($this->urls->homePath($locale)),
                ]),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return self::clean([
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
            'url' => $this->root(),
            'name' => $this->cv->profile()->full_name,
            'inLanguage' => $this->locales->active(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function person(string $locale): array
    {
        $profile = $this->cv->profile();
        $headlines = $profile->headlineList($locale);
        $location = $profile->t('location', $locale);

        $person = [
            '@type' => 'Person',
            '@id' => $this->personId(),
            'name' => $profile->full_name,
            'url' => url($this->urls->homePath($locale)),
            'image' => $this->images->url($profile->photo),
            'jobTitle' => count($headlines) === 1 ? $headlines[0] : $headlines,
            'description' => $this->richText->plain($profile->t('bio', $locale), 300),
            'sameAs' => $this->cv->socials()->map(fn (SocialLink $link): string => $link->url)->values()->all(),
            'address' => $location !== null || $profile->country_code !== null
                ? self::clean(['@type' => 'PostalAddress', 'addressLocality' => $location, 'addressCountry' => $profile->country_code])
                : null,
            'worksFor' => $this->organizations($this->cv->currentWork()->map(fn (Experience $experience): ?Organization => $experience->organization)),
            'alumniOf' => $this->organizations($this->cv->educations()->map(fn (Education $education): ?Organization => $education->organization), 'EducationalOrganization'),
            'knowsLanguage' => $this->cv->languageSkills()->map(fn (LanguageSkill $skill): array => [
                '@type' => 'Language',
                'name' => $skill->displayName($locale),
                'alternateName' => $skill->language_code,
            ])->values()->all(),
            'hasCredential' => $this->cv->certifications()->map(fn (Certification $certification): array => $this->credential($certification, $locale))->values()->all(),
            'knowsAbout' => Skill::query()->where('is_featured', true)->ordered()->get()
                ->map(fn (Skill $skill): ?string => $skill->t('name', $locale))
                ->filter()->values()->all(),
        ];

        // Dati personali: solo se l'admin li ha attivati esplicitamente
        if ($this->settings->bool('jsonld.include_email') && filled($profile->email)) {
            $person['email'] = $profile->email;
        }

        if ($this->settings->bool('jsonld.include_phone') && filled($profile->phone)) {
            $person['telephone'] = $profile->phone;
        }

        if ($this->settings->bool('jsonld.include_birthdate') && $profile->birth_date !== null) {
            $person['birthDate'] = $profile->birth_date->format('Y-m-d');
        }

        return self::clean($person);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function publications(string $locale): array
    {
        return $this->cv->publications()->map(function (Publication $publication) use ($locale): array {
            $coauthors = array_values(array_filter(array_map('trim', explode(',', (string) $publication->coauthors))));

            return self::clean([
                '@type' => $publication->type->schemaType(),
                'name' => $publication->t('title', $locale),
                'author' => [
                    ['@id' => $this->personId()],
                    ...array_map(fn (string $name): array => ['@type' => 'Person', 'name' => $name], $coauthors),
                ],
                'datePublished' => $publication->published_on?->format('Y-m-d'),
                'url' => $publication->url ?? $this->images->url($publication->file),
                'inLanguage' => $publication->language_code,
                'publisher' => ($venue = $publication->t('venue', $locale)) !== null ? ['@type' => 'Organization', 'name' => $venue] : null,
                'description' => $this->richText->plain($publication->t('description', $locale), 300),
            ]);
        })->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function breadcrumbList(Page $page): array
    {
        $items = [];

        foreach ($page->breadcrumbs as $position => $crumb) {
            $items[] = self::clean([
                '@type' => 'ListItem',
                'position' => $position + 1,
                'name' => $crumb['label'],
                'item' => $crumb['url'] ?? $page->canonical,
            ]);
        }

        return ['@type' => 'BreadcrumbList', '@id' => $page->canonical.'#breadcrumb', 'itemListElement' => $items];
    }

    /**
     * @return array<string, mixed>
     */
    private function credential(Certification $certification, string $locale): array
    {
        return self::clean([
            '@type' => 'EducationalOccupationalCredential',
            'name' => $certification->t('title', $locale),
            'credentialCategory' => __('site.certifications.types.'.$certification->type->value),
            'recognizedBy' => $certification->organization !== null ? $this->organization($certification->organization) : null,
            'dateCreated' => $certification->issued_on?->format('Y-m-d'),
            'expires' => $certification->expires_on?->format('Y-m-d'),
            'identifier' => $certification->credential_id,
            'url' => $certification->verification_url,
        ]);
    }

    /**
     * @param  iterable<Organization|null>  $organizations
     * @return list<array<string, mixed>>
     */
    private function organizations(iterable $organizations, string $type = 'Organization'): array
    {
        $unique = [];

        foreach ($organizations as $organization) {
            if ($organization !== null) {
                $unique[$organization->id] = $this->organization($organization, $type);
            }
        }

        return array_values($unique);
    }

    /**
     * @return array<string, mixed>
     */
    private function organization(Organization $organization, string $type = 'Organization'): array
    {
        return self::clean([
            '@type' => $type,
            'name' => $organization->name,
            'url' => $organization->website,
            'logo' => $this->images->url($organization->logo),
        ]);
    }

    /**
     * Toglie i valori vuoti (null, "", []).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function clean(array $data): array
    {
        return array_filter($data, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
