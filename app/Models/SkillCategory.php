<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Skill category (e.g. Expertise, Soft skills, Tools).
 *
 * @property int $id
 * @property string $key
 * @property int $sort_order
 */
class SkillCategory extends Model implements Translatable
{
    use HasTranslations, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['name'];

    protected $fillable = ['key', 'name', 'sort_order'];

    /** @return HasMany<Skill, $this> */
    public function skills(): HasMany
    {
        return $this->hasMany(Skill::class);
    }
}
