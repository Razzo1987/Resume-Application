<?php

namespace App\Filament\Resources\Education\Tables;

use App\Filament\Support\Columns;
use App\Models\Education;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EducationTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::modifyQuery(...))
            ->columns([
                Columns::translated('degree'),
                Columns::translated('field_of_study'),
                TextColumn::make('organization.name')->label(__('admin.fields.institution'))->searchable(),
                TextColumn::make('period')
                    ->label(__('admin.fields.period'))
                    ->state(fn (Education $record): string => ($record->start_date?->format('m/Y') ?? '...').' - '.($record->end_date?->format('m/Y') ?? __('admin.labels.ongoing'))),
                TextColumn::make('grade')
                    ->label(__('admin.fields.grade'))
                    ->state(fn (Education $record): ?string => $record->grade === null ? null : $record->grade.($record->grade_scale ? '/'.$record->grade_scale : '').($record->cum_laude ? ' L' : '')),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * Eager-loaded relations and default sort order of the list.
     *
     * @param  Builder<Education>  $query
     * @return Builder<Education>
     */
    private static function modifyQuery(Builder $query): Builder
    {
        return $query->with('organization')->chronological();
    }
}
