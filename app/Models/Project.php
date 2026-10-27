<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsChronological;
use App\Support\Translatable\GeneratesSlugs;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * Project, linked to the experience it was carried out in and to the client.
 *
 * @property int $id
 * @property int|null $experience_id
 * @property int|null $client_id
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property string|null $image
 * @property bool $is_featured
 * @property bool $is_visible
 * @property-read Experience|null $experience
 * @property-read Organization|null $client
 */
class Project extends Model implements Translatable
{
    use GeneratesSlugs, HasTranslations, HasVisibility, IsChronological;

    /** @var list<string> */
    protected array $translatable = ['title', 'role', 'sector', 'summary', 'details', 'image_alt', 'slug', 'seo_title', 'seo_description'];

    protected $fillable = [
        'experience_id', 'client_id', 'title', 'role', 'sector', 'start_date', 'end_date',
        'summary', 'details', 'image', 'image_alt', 'slug', 'seo_title', 'seo_description',
        'is_featured', 'is_visible',
    ];

    protected $attributes = [
        'is_featured' => false,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Experience, $this> */
    public function experience(): BelongsTo
    {
        return $this->belongsTo(Experience::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'client_id');
    }

    /** @return BelongsToMany<Skill, $this> */
    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class);
    }

    /**
     * Has a dedicated page only if there is a detail text or an image.
     */
    public function hasDetailPage(): bool
    {
        return filled($this->t('details')) || filled($this->image);
    }

    public function slugSource(string $locale): ?string
    {
        return $this->t('title', $locale);
    }
}
