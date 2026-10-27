<?php

declare(strict_types=1);

namespace App\Site\Cache;

use App\Site\Blog;
use App\Themes\ThemeManager;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * HTML cache of the public pages (files in storage/framework/cache/pages).
 * It is cleared on every content change; it expires after a few hours anyway
 * because some values depend on the date (durations, years of experience).
 */
final class PageCache
{
    public const string STORE = 'pages';

    public const int TTL = 6 * 3600;

    private ?string $assetsVersion = null;

    /**
     * @return array{content: string, type: string, etag: string, time: int}|null
     */
    public function get(string $key): ?array
    {
        if (! $this->enabled()) {
            return null;
        }

        try {
            $entry = $this->store()->get($key);
        } catch (Throwable) {
            return null;
        }

        /** @var array{content: string, type: string, etag: string, time: int}|null */
        return is_array($entry) ? $entry : null;
    }

    public function put(string $key, string $content, string $type): string
    {
        $etag = '"'.hash('xxh128', $content).'"';

        if ($this->enabled()) {
            try {
                $this->store()->put($key, ['content' => $content, 'type' => $type, 'etag' => $etag, 'time' => time()], $this->ttl());
            } catch (Throwable) {
                // Cache not writable: the page is still generated on every request
            }
        }

        return $etag;
    }

    /**
     * Key of a page. It includes the asset version of the theme and of the application:
     * after an update (new CSS/JS files with a different hash) the old pages are no longer served.
     */
    public function key(string $url): string
    {
        return 'page.'.sha1($this->assetsVersion().'|'.$url);
    }

    public function assetsVersion(): string
    {
        if ($this->assetsVersion === null) {
            $theme = app(ThemeManager::class)->active();
            $manifest = public_path($theme->buildDirectory().'/manifest.json');
            $version = base_path('VERSION');

            $this->assetsVersion = implode('|', [
                $theme->name,
                is_file($manifest) ? (string) filemtime($manifest) : '-',
                is_file($version) ? trim((string) file_get_contents($version)) : '-',
            ]);
        }

        return $this->assetsVersion;
    }

    /**
     * Seconds of validity: at most TTL, but never past the next scheduled publication of an article.
     */
    public function ttl(): int
    {
        try {
            $next = app(Blog::class)->nextScheduledAt();
        } catch (Throwable) {
            $next = null;
        }

        if ($next === null) {
            return self::TTL;
        }

        return max(1, min(self::TTL, (int) ceil(now()->diffInSeconds($next, false))));
    }

    public function flush(): void
    {
        try {
            $this->store()->clear();
        } catch (Throwable) {
            // nothing to clear
        }
    }

    public function enabled(): bool
    {
        return (bool) config('resume.page_cache', true);
    }

    private function store(): Repository
    {
        return Cache::store(self::STORE);
    }
}
