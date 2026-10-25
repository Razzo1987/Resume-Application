<?php

namespace App\Filament\Resources\Experiences\Tables;

use App\Filament\Support\Columns;
use App\Models\Experience;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExperiencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::modifyQuery(...))
            ->columns([
                Columns::translated('role'),
                TextColumn::make('organization.name')->label(__('admin.fields.organization'))->searchable(),
                TextColumn::make('period')
                    ->label(__('admin.fields.period'))
                    ->state(fn (Experience $record): string => $record->start_date->format('m/Y').' - '.($record->end_date?->format('m/Y') ?? __('admin.labels.ongoing'))),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->filters([
                TernaryFilter::make('is_visible')->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Relazioni caricate in anticipo e ordinamento predefinito dell'elenco.
     *
     * @param  Builder<Experience>  $query
     * @return Builder<Experience>
     */
    private static function modifyQuery(Builder $query): Builder
    {
        return $query->with('organization')->chronological();
    }
}
