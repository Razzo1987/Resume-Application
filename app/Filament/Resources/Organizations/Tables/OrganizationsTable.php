<?php

namespace App\Filament\Resources\Organizations\Tables;

use App\Enums\OrganizationType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')->label(__('admin.fields.logo'))->disk('uploads')->imageHeight(32),
                TextColumn::make('name')->label(__('admin.fields.name'))->searchable()->sortable(),
                TextColumn::make('type')->label(__('admin.fields.organization_type'))->badge(),
                TextColumn::make('website')->label(__('admin.fields.website'))->url(fn ($state) => $state, true)->limit(40)->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([
                SelectFilter::make('type')->label(__('admin.fields.organization_type'))->options(OrganizationType::class),
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
}
