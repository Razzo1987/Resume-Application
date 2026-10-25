<?php

namespace App\Filament\Resources\Certifications\Tables;

use App\Enums\CertificationType;
use App\Filament\Support\Columns;
use App\Models\Certification;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CertificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(self::modifyQuery(...))
            ->columns([
                ImageColumn::make('badge')
                    ->label(__('admin.fields.badge'))
                    ->disk('uploads')
                    // Stessa "scatola" per tutti: quadrati e rettangolari restano proporzionati e allineati
                    ->imageWidth(80)
                    ->imageHeight(40)
                    ->extraImgAttributes(['style' => 'object-fit: contain; object-position: left center;'])
                    ->toggleable(),
                Columns::translated('title'),
                TextColumn::make('organization.name')->label(__('admin.fields.issuer'))->searchable(),
                TextColumn::make('type')->label(__('admin.fields.certification_type'))->badge(),
                TextColumn::make('issued_on')->label(__('admin.fields.issued_on'))->date('m/Y')->sortable(),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
                Columns::missingTranslations(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('admin.fields.certification_type'))->options(CertificationType::class),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    /**
     * Relazioni caricate in anticipo e ordinamento predefinito dell'elenco.
     *
     * @param  Builder<Certification>  $query
     * @return Builder<Certification>
     */
    private static function modifyQuery(Builder $query): Builder
    {
        return $query->with('organization')->latestFirst();
    }
}
