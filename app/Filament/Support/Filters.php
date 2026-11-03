<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Models\Language;
use App\Support\Languages\Locales;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Filters that recur in the admin tables.
 */
class Filters
{
    /**
     * "To translate into...": only the records with text in some language that is missing in the chosen one
     * (the same rule as the "Missing translations" column). The tables of the CV are small, so the check is made on the
     * records instead of on the JSON columns, where rich text with only tags would look filled.
     */
    public static function missingTranslations(): SelectFilter
    {
        return SelectFilter::make('missing_translation')
            ->label(__('admin.filters.missing_translation'))
            ->placeholder(__('admin.filters.missing_translation_any'))
            ->options(fn (): array => app(Locales::class)->activeLanguages()->mapWithKeys(fn (Language $language): array => [$language->code => $language->name.' ('.$language->code.')'])->all())
            ->query(function (Builder $query, array $data): Builder {
                $locale = $data['value'] ?? null;

                if (! is_string($locale) || $locale === '') {
                    return $query;
                }

                $keys = (clone $query)->get()
                    ->filter(fn (Model $record): bool => method_exists($record, 'missingTranslations') && $record->missingTranslations($locale) !== [])
                    ->modelKeys();

                return $query->whereKey($keys);
            });
    }
}
