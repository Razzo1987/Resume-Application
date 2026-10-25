<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ExperienceKind;
use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsChronological;
use App\Support\Translatable\GeneratesSlugs;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Esperienza di lavoro o di volontariato.
 *
 * @property int $id
 * @property ExperienceKind $kind
 * @property int|null $organization_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property bool $is_visible
 * @property-read Organization|null $organization
 */
class Experience extends Model implements Translatable
{
    use GeneratesSlugs, HasTranslations, HasVisibility, IsChronological;

    /** @var list<string> */
    protected array $translatable = ['role', 'location', 'summary', 'details', 'slug', 'seo_title', 'seo_description'];

    protected $fillable = [
        'kind', 'organization_id', 'role', 'location', 'start_date', 'end_date',
        'summary', 'details', 'slug', 'seo_title', 'seo_description', 'is_visible',
    ];

    protected $attributes = [
        'kind' => 'work',
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'kind' => ExperienceKind::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return HasMany<Project, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWork(Builder $query): Builder
    {
        return $query->where('kind', ExperienceKind::Work);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVolunteering(Builder $query): Builder
    {
        return $query->where('kind', ExperienceKind::Volunteering);
    }

    public function slugSource(string $locale): ?string
    {
        $role = $this->t('role', $locale);
        $organization = $this->organization_id !== null ? Organization::query()->find($this->organization_id)?->name : null;

        return trim(implode(' ', array_filter([$role, $organization])));
    }
}
