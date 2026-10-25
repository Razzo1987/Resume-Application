<?php

declare(strict_types=1);

namespace App\Site\Cache;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Cache HTML delle pagine pubbliche (file in storage/framework/cache/pages).
 * Si svuota a ogni modifica dei contenuti; scade comunque dopo alcune ore
 * perché alcuni valori dipendono dalla data (durate, anni di esperienza).
 */
final class PageCache
{
    public const string STORE = 'pages';

    public const int TTL = 6 * 3600;

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
                $this->store()->put($key, ['content' => $content, 'type' => $type, 'etag' => $etag, 'time' => time()], self::TTL);
            } catch (Throwable) {
                // Cache non scrivibile: la pagina si genera comunque a ogni richiesta
            }
        }

        return $etag;
    }

    public function flush(): void
    {
        try {
            $this->store()->clear();
        } catch (Throwable) {
            // niente da svuotare
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
