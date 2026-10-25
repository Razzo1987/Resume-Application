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

    protected static function booted(): void
    {
        // "Pubblicato" senza data: si pubblica adesso
        static::saving(function (Article $article): void {
            if ($article->status === ArticleStatus::Published && $article->published_at === null) {
                $article->published_at = now();
            }
        });
    }

    /**
     * Articoli scritti in una lingua (titolo presente): nel blog di quella lingua compaiono solo questi.
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
     * Minuti di lettura stimati (200 parole al minuto).
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
     * Lo slug nasce solo dal titolo scritto in quella lingua: un articolo non tradotto non ha indirizzo in quella lingua.
     */
    public function slugSource(string $locale): ?string
    {
        return $this->t('title', $locale, false);
    }
}
