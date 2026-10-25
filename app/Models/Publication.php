<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PublicationType;
use App\Models\Concerns\HasVisibility;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pubblicazione o intervento (articolo, paper, tesi, talk, webinar, ...).
 *
 * @property int $id
 * @property PublicationType $type
 * @property Carbon|null $published_on
 * @property string|null $coauthors
 * @property string|null $url
 * @property string|null $file
 * @property string|null $language_code
 * @property bool $is_featured
 * @property bool $is_visible
 */
class Publication extends Model implements Translatable
{
    use HasTranslations, HasVisibility;

    /** @var list<string> */
    protected array $translatable = ['title', 'venue', 'description'];

    protected $fillable = [
        'type', 'title', 'venue', 'published_on', 'coauthors', 'url', 'file',
        'description', 'language_code', 'is_featured', 'is_visible',
    ];

    protected $attributes = [
        'type' => 'article',
        'is_featured' => false,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'published_on' => 'date',
            'is_featured' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('published_on')->orderByDesc('id');
    }
}
