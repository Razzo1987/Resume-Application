<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Languages\Locales;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Columns that recur in the admin tables.
 */
class Columns
{
    /**
     * Text translated into the site's default language, searchable in all languages.
     */
    public static function translated(string $field, ?string $label = null): TextColumn
    {
        return TextColumn::make($field)
            ->label($label ?? __("admin.fields.{$field}"))
            ->state(fn (Model $record): ?string => method_exists($record, 't')
                ? strip_tags((string) $record->t($field, app(Locales::class)->default()))
                : null)
            ->searchable(query: fn (Builder $query, string $search): Builder => $query->where($field, 'like', '%'.$search.'%'))
            ->wrap();
    }

    /**
     * Languages with missing translations (badges), to spot the content to complete.
     */
    public static function missingTranslations(): TextColumn
    {
        return TextColumn::make('missing_translations')
            ->label(__('admin.fields.missing_translations'))
            ->state(function (Model $record): array {
                if (! method_exists($record, 'missingTranslations')) {
                    return [];
                }

                return array_values(array_filter(
                    app(Locales::class)->active(),
                    fn (string $code): bool => $record->missingTranslations($code) !== [],
                ));
            })
            ->badge()
            ->color('warning')
            ->toggleable();
    }
}
