<?php

declare(strict_types=1);

namespace App\Support\Languages;

use App\Models\Language;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lingue attive e lingua di default del sito (dalla tabella languages).
 * Prima dell'installazione ripiega su APP_LOCALE / APP_FALLBACK_LOCALE.
 */
class Locales
{
    /** @var Collection<int, Language>|null */
    private ?Collection $languages = null;

    /**
     * @return Collection<int, Language>
     */
    public function languages(): Collection
    {
        return $this->languages ??= $this->load();
    }

    /**
     * Codici delle lingue attive, la lingua di default per prima.
     *
     * @return list<string>
     */
    public function active(): array
    {
        $codes = $this->languages()
            ->where('is_active', true)
            ->sortBy([['is_default', 'desc'], ['sort_order', 'asc']])
            ->pluck('code')
            ->values()
            ->all();

        return $codes !== [] ? $codes : [$this->fallbackDefault()];
    }

    public function default(): string
    {
        $default = $this->languages()->firstWhere('is_default', true);

        return $default instanceof Language ? $default->code : $this->fallbackDefault();
    }

    public function isActive(string $code): bool
    {
        return in_array($code, $this->active(), true);
    }

    public function find(string $code): ?Language
    {
        $language = $this->languages()->firstWhere('code', $code);

        return $language instanceof Language ? $language : null;
    }

    public function flush(): void
    {
        $this->languages = null;
    }

    /**
     * @return Collection<int, Language>
     */
    private function load(): Collection
    {
        try {
            if (! Schema::hasTable('languages')) {
                return new Collection;
            }

            return Language::query()->orderBy('sort_order')->get();
        } catch (Throwable) {
            return new Collection;
        }
    }

    private function fallbackDefault(): string
    {
        return (string) config('app.locale', 'en-GB');
    }
}
