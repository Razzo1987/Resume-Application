<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $skill_category_id
 * @property bool $is_featured
 * @property int $sort_order
 */
class Skill extends Model implements Translatable
{
    use HasTranslations, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['skill_category_id', 'name', 'is_featured', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
        ];
    }

    /** @return BelongsTo<SkillCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SkillCategory::class, 'skill_category_id');
    }

    /** @return BelongsToMany<Experience, $this> */
    public function experiences(): BelongsToMany
    {
        return $this->belongsToMany(Experience::class);
    }

    /** @return BelongsToMany<Project, $this> */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class);
    }
}
