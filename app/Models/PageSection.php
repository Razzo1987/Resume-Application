<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PageSectionKey;
use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Section of the CV page: enabled/disabled, order, custom title, menu item.
 *
 * @property int $id
 * @property PageSectionKey $key
 * @property bool $is_enabled
 * @property bool $in_menu
 * @property int $sort_order
 */
class PageSection extends Model implements Translatable
{
    use HasTranslations, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['title'];

    protected $fillable = ['key', 'is_enabled', 'in_menu', 'sort_order', 'title'];

    protected function casts(): array
    {
        return [
            'key' => PageSectionKey::class,
            'is_enabled' => 'boolean',
            'in_menu' => 'boolean',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * Custom title or, if empty, the one from the language pack.
     */
    public function displayTitle(?string $locale = null): string
    {
        return $this->t('title', $locale) ?? __($this->key->siteStringKey(), locale: $locale);
    }

    /**
     * Creates the missing sections in the default order (idempotent).
     */
    public static function ensureDefaults(): void
    {
        foreach (PageSectionKey::cases() as $position => $key) {
            self::query()->firstOrCreate(['key' => $key], [
                'is_enabled' => $key !== PageSectionKey::LatestArticles,
                // Latest articles: the "Blog" menu item is there even with the section turned off in the CV
                'in_menu' => $key !== PageSectionKey::Highlights,
                'sort_order' => ($position + 1) * 10,
            ]);
        }
    }
}
