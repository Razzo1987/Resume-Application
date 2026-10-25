<?php

declare(strict_types=1);

namespace App\Support\Translatable;

use App\Support\Languages\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Genera lo slug per ogni lingua attiva quando è vuoto (es. dal titolo), unico per lingua.
 * Il modello deve usare HasTranslations, avere un campo tradotto "slug" e definire slugSource().
 *
 * @phpstan-require-extends Model
 */
trait GeneratesSlugs
{
    abstract public function slugSource(string $locale): ?string;

    public static function bootGeneratesSlugs(): void
    {
        static::saving(function (self $model): void {
            $model->fillMissingSlugs();
        });
    }

    public function fillMissingSlugs(): void
    {
        foreach (app(Locales::class)->active() as $locale) {
            $current = $this->translations('slug')[$locale] ?? null;
            $base = is_string($current) && trim($current) !== ''
                ? Str::slug($current, '-', strtok($locale, '-') ?: null)
                : Str::slug((string) $this->slugSource($locale), '-', strtok($locale, '-') ?: null);

            if ($base === '') {
                continue;
            }

            $this->setTranslation('slug', $locale, $this->uniqueSlug($base, $locale));
        }
    }

    private function uniqueSlug(string $base, string $locale): string
    {
        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where("slug->{$locale}", $slug)
            ->when($this->exists, fn ($query) => $query->whereKeyNot($this->getKey()))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
