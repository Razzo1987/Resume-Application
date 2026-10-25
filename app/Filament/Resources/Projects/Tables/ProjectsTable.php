<?php

namespace App\Filament\Resources\Projects\Tables;

use App\Filament\Support\Columns;
use App\Models\Project;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::modifyQuery(...))
            ->columns([
                Columns::translated('title'),
                TextColumn::make('client.name')->label(__('admin.fields.client'))->searchable(),
                TextColumn::make('experience.organization.name')->label(__('admin.fields.experience'))->toggleable(),
                TextColumn::make('period')
                    ->label(__('admin.fields.period'))
                    ->state(fn (Project $record): string => $record->start_date->format('m/Y').' – '.($record->end_date?->format('m/Y') ?? __('admin.labels.ongoing'))),
                ToggleColumn::make('is_featured')->label(__('admin.fields.is_featured'))->toggleable(),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->filters([
                SelectFilter::make('client_id')->label(__('admin.fields.client'))->relationship('client', 'name')->preload(),
                TernaryFilter::make('is_visible')->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * Relazioni caricate in anticipo e ordinamento predefinito dell'elenco.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    private static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['client', 'experience.organization'])->chronological();
    }
}
