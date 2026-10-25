<?php

declare(strict_types=1);

namespace App\Support\Translatable;

use App\Support\Languages\Locales;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Campi tradotti salvati in colonne JSON: {"it-IT": "...", "en-GB": "..."}.
 *
 * Il modello dichiara i campi in `protected array $translatable = [...]`.
 * Date, link, immagini e relazioni NON si traducono: esistono una sola volta per entità.
 *
 * @phpstan-require-extends Model
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        foreach ($this->getTranslatableAttributes() as $field) {
            $this->mergeCasts([$field => 'array']);
        }
    }

    /**
     * @return list<string>
     */
    public function getTranslatableAttributes(): array
    {
        return $this->translatable;
    }

    /**
     * Valore tradotto. Se manca nella lingua richiesta (e $fallback è vero) usa la lingua
     * di default del sito e poi la prima traduzione disponibile.
     */
    public function t(string $field, ?string $locale = null, bool $fallback = true): ?string
    {
        $values = $this->translations($field);
        $locale ??= app()->getLocale();

        if ($this->filledValue($values[$locale] ?? null)) {
            return (string) $values[$locale];
        }

        if (! $fallback) {
            return null;
        }

        $default = app(Locales::class)->default();

        if ($this->filledValue($values[$default] ?? null)) {
            return (string) $values[$default];
        }

        foreach ($values as $value) {
            if ($this->filledValue($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function translations(string $field): array
    {
        $values = $this->getAttribute($field);

        return is_array($values) ? $values : [];
    }

    public function hasTranslation(string $field, string $locale): bool
    {
        return $this->filledValue($this->translations($field)[$locale] ?? null);
    }

    public function setTranslation(string $field, string $locale, ?string $value): static
    {
        $values = $this->translations($field);
        $values[$locale] = $value;
        $this->setAttribute($field, $values);

        return $this;
    }

    /**
     * Campi tradotti che hanno un valore in almeno una lingua ma mancano nella lingua indicata.
     *
     * @return list<string>
     */
    public function missingTranslations(string $locale): array
    {
        $missing = [];

        foreach ($this->getTranslatableAttributes() as $field) {
            $values = array_filter($this->translations($field), fn (mixed $value): bool => $this->filledValue($value));

            if ($values !== [] && ! isset($values[$locale])) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Ricerca su un campo tradotto, es. lo slug: Project::whereTranslation('slug', 'it-IT', 'data-catalog').
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeWhereTranslation(Builder $query, string $field, string $locale, string $value): Builder
    {
        return $query->where("{$field}->{$locale}", $value);
    }

    private function filledValue(mixed $value): bool
    {
        return is_string($value) ? trim(strip_tags($value)) !== '' : $value !== null && $value !== [];
    }
}
