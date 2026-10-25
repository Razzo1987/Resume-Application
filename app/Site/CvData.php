<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\PageSectionKey;
use App\Models\Certification;
use App\Models\Education;
use App\Models\Experience;
use App\Models\Highlight;
use App\Models\Interest;
use App\Models\LanguageSkill;
use App\Models\PageSection;
use App\Models\Profile;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Skill;
use App\Models\SocialLink;
use App\Site\Content\RichText;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection as BaseCollection;

/**
 * Contenuti del CV per la richiesta corrente, caricati una sola volta e solo quando servono
 * (con le relazioni già incluse, per evitare query ripetute).
 */
final class CvData
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(private readonly RichText $richText) {}

    public function profile(): Profile
    {
        return $this->remember('profile', fn (): Profile => Profile::current());
    }

    /**
     * @return Collection<int, SocialLink>
     */
    public function socials(): Collection
    {
        return $this->remember('socials', fn (): Collection => SocialLink::query()->visible()->ordered()->get());
    }

    /**
     * Sezioni attive e non vuote, nell'ordine scelto in admin.
     *
     * @return BaseCollection<int, PageSection>
     */
    public function sections(): BaseCollection
    {
        return $this->remember('sections', function (): BaseCollection {
            $sections = PageSection::query()->enabled()->ordered()->get();

            if (PageSection::query()->count() === 0) {
                PageSection::ensureDefaults();
                $sections = PageSection::query()->enabled()->ordered()->get();
            }

            return $sections->filter(fn (PageSection $section): bool => $this->hasContent($section->key))->values();
        });
    }

    /**
     * Voci del menu: sezioni attive, non vuote e segnate "nel menu".
     *
     * @return BaseCollection<int, PageSection>
     */
    public function menu(): BaseCollection
    {
        return $this->sections()->filter(fn (PageSection $section): bool => $section->in_menu)->values();
    }

    /**
     * Titolo di una sezione: quello personalizzato in admin oppure quello del language pack.
     */
    public function sectionTitle(PageSectionKey $key): string
    {
        $sections = $this->remember('allSections', fn (): Collection => PageSection::query()->get()->keyBy(fn (PageSection $section): string => $section->key->value));
        $section = $sections->get($key->value);

        return $section instanceof PageSection ? $section->displayTitle() : (string) __($key->siteStringKey());
    }

    public function hasContent(PageSectionKey $key): bool
    {
        return match ($key) {
            PageSectionKey::About => $this->richText->hasText($this->profile()->t('bio'))
                || filled($this->profile()->email)
                || filled($this->profile()->phone)
                || filled($this->profile()->t('location')),
            PageSectionKey::Highlights => $this->highlights()->isNotEmpty(),
            PageSectionKey::Experience => $this->experiences()->isNotEmpty(),
            PageSectionKey::Volunteering => $this->volunteering()->isNotEmpty(),
            PageSectionKey::Projects => $this->projects()->isNotEmpty(),
            PageSectionKey::Education => $this->educations()->isNotEmpty(),
            PageSectionKey::Certifications => $this->certifications()->isNotEmpty(),
            PageSectionKey::Languages => $this->languageSkills()->isNotEmpty(),
            PageSectionKey::Publications => $this->publications()->isNotEmpty(),
            PageSectionKey::Interests => $this->interests()->isNotEmpty(),
            // Il blog arriva in M3
            PageSectionKey::LatestArticles => false,
        };
    }

    /**
     * Numeri chiave con un valore calcolabile.
     *
     * @return BaseCollection<int, Highlight>
     */
    public function highlights(): BaseCollection
    {
        return $this->remember('highlights', fn (): BaseCollection => Highlight::query()->visible()->ordered()->get()
            ->filter(fn (Highlight $highlight): bool => $highlight->resolvedValue() !== null)
            ->values());
    }

    /**
     * @return Collection<int, Experience>
     */
    public function experiences(): Collection
    {
        return $this->remember('experiences', fn (): Collection => $this->experienceQuery()->work()->get());
    }

    /**
     * @return Collection<int, Experience>
     */
    public function volunteering(): Collection
    {
        return $this->remember('volunteering', fn (): Collection => $this->experienceQuery()->volunteering()->get());
    }

    /**
     * @return Collection<int, Project>
     */
    public function projects(): Collection
    {
        return $this->remember('projects', fn (): Collection => Project::query()
            ->visible()
            ->chronological()
            ->with(['client', 'experience.organization', 'skills' => fn (BelongsToMany $query) => self::skillsWithCategory($query)])
            ->get());
    }

    /**
     * @return Collection<int, Education>
     */
    public function educations(): Collection
    {
        return $this->remember('educations', fn (): Collection => Education::query()->visible()->chronological()->with('organization')->get());
    }

    /**
     * @return Collection<int, Certification>
     */
    public function certifications(): Collection
    {
        return $this->remember('certifications', fn (): Collection => Certification::query()->visible()->latestFirst()->with('organization')->get());
    }

    /**
     * Lingue conosciute: prima le madrelingua.
     *
     * @return Collection<int, LanguageSkill>
     */
    public function languageSkills(): Collection
    {
        return $this->remember('languageSkills', fn (): Collection => LanguageSkill::query()
            ->visible()
            ->orderByDesc('is_native')
            ->ordered()
            ->with('certification')
            ->get());
    }

    /**
     * @return Collection<int, Publication>
     */
    public function publications(): Collection
    {
        return $this->remember('publications', fn (): Collection => Publication::query()->visible()->latestFirst()->get());
    }

    /**
     * @return Collection<int, Interest>
     */
    public function interests(): Collection
    {
        return $this->remember('interests', fn (): Collection => Interest::query()->visible()->ordered()->get());
    }

    /**
     * Esperienze di lavoro in corso (per "worksFor" nei dati strutturati).
     *
     * @return Collection<int, Experience>
     */
    public function currentWork(): Collection
    {
        return $this->experiences()->filter(fn (Experience $experience): bool => $experience->end_date === null)->values();
    }

    /**
     * @return Builder<Experience>
     */
    private function experienceQuery(): Builder
    {
        return Experience::query()
            ->visible()
            ->chronological()
            ->with(['organization', 'skills' => fn (BelongsToMany $query) => self::skillsWithCategory($query)])
            ->withCount(['projects as visible_projects_count' => fn (Builder $query): Builder => $query->where('is_visible', true)]);
    }

    /**
     * Competenze con la categoria, nell'ordine scelto in admin.
     *
     * @template TParent of Model
     *
     * @param  BelongsToMany<Skill, TParent>  $query
     */
    private static function skillsWithCategory(BelongsToMany $query): void
    {
        $query->with('category')->orderBy('skills.sort_order')->orderBy('skills.id');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $resolver
     * @return T
     */
    private function remember(string $key, callable $resolver): mixed
    {
        if (! array_key_exists($key, $this->memo)) {
            $this->memo[$key] = $resolver();
        }

        /** @var T */
        return $this->memo[$key];
    }
}
