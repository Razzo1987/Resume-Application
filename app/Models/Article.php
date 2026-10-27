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
 * Article of the mini-blog. Published if the status is "published" and the publication date has passed.
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

    protected static function booted(): void
    {
        // "Published" without a date: it is published now
        static::saving(function (Article $article): void {
            if ($article->status === ArticleStatus::Published && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    /**
     * Articles written in a language (title present): only these appear in the blog of that language.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeAvailableIn(Builder $query, string $locale): Builder
    {
        return $query->whereNotNull("title->{$locale}")->where("title->{$locale}", '!=', '');
    }

    public function isAvailableIn(string $locale): bool
    {
        return filled($this->t('title', $locale, false));
    }

    /**
     * Estimated reading time in minutes (200 words per minute).
     */
    public function readingMinutes(?string $locale = null): int
    {
        $words = str_word_count(strip_tags((string) $this->t('body', $locale)));

        return max(1, (int) ceil($words / 200));
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
            && ! $this->published_at->isFuture();
    }

    /**
     * The slug comes only from the title written in that language:
     * an untranslated article has no URL in that language.
     */
    public function slugSource(string $locale): ?string
    {
        return $this->t('title', $locale, false);
    }
}
