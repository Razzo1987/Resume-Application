<?php

namespace App\Filament\Resources\Publications\Tables;

use App\Enums\PublicationType;
use App\Filament\Support\Columns;
use App\Models\Publication;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PublicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::modifyQuery(...))
            ->columns([
                Columns::translated('title'),
                TextColumn::make('type')->label(__('admin.fields.publication_type'))->badge(),
                TextColumn::make('published_on')->label(__('admin.fields.published_on'))->date('m/Y')->sortable(),
                ToggleColumn::make('is_featured')->label(__('admin.fields.is_featured'))->toggleable(),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.fields.publication_type'))->options(PublicationType::class),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * Relazioni caricate in anticipo e ordinamento predefinito dell'elenco.
     *
     * @param  Builder<Publication>  $query
     * @return Builder<Publication>
     */
    private static function modifyQuery(Builder $query): Builder
    {
        return $query->latestFirst();
    }
}
