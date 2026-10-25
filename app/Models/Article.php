<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleStatus;
use App\Support\Translatable\GeneratesSlugs;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Articolo del mini-blog. Pubblicato se lo stato è "published" e la data di pubblicazione è passata.
 *
 * @property int $id
 * @property string|null $cover_image
 * @property ArticleStatus $status
 * @property Carbon|null $published_at
 */
class Article extends Model implements Translatable
{
    use GeneratesSlugs, HasTranslations;

    /** @var list<string> */
    protected array $translatable = ['title', 'slug', 'excerpt', 'body', 'cover_alt'];

    protected $fillable = ['title', 'slug', 'excerpt', 'body', 'cover_image', 'cover_alt', 'status', 'published_at'];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ArticleStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published
            && $this->published_at !== null
            && $this->published_at->isPast();
    }

    public function slugSource(string $locale): ?string
    {
        return $this->t('title', $locale);
    }
}
