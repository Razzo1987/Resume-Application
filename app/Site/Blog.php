<?php

declare(strict_types=1);

namespace App\Site;

use App\Enums\ArticleStatus;
use App\Models\Article;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

/**
 * Blog articles visible in a language: published (past date) and written in that language.
 */
final class Blog
{
    public const int PER_PAGE = 10;

    /** @var array<string, bool> */
    private array $has = [];

    /**
     * @return Builder<Article>
     */
    public function query(string $locale): Builder
    {
        return Article::query()->published()->availableIn($locale)->orderByDesc('published_at')->orderByDesc('id');
    }

    public function hasArticles(string $locale): bool
    {
        return $this->has[$locale] ??= $this->query($locale)->exists();
    }

    /**
     * @return LengthAwarePaginator<int, Article>
     */
    public function page(string $locale, int $page): LengthAwarePaginator
    {
        return $this->query($locale)->paginate(self::PER_PAGE, page: $page);
    }

    /**
     * @return Collection<int, Article>
     */
    public function latest(string $locale, int $count = 3): Collection
    {
        return $this->query($locale)->limit($count)->get();
    }

    /**
     * Next scheduled publication: cached pages must not outlive it.
     */
    public function nextScheduledAt(): ?Carbon
    {
        $next = Article::query()
            ->where('status', ArticleStatus::Published)
            ->where('published_at', '>', now())
            ->min('published_at');

        return is_string($next) ? Carbon::parse($next) : null;
    }
}
