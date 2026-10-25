<?php

namespace App\Filament\Resources\Articles\Tables;

use App\Enums\ArticleStatus;
use App\Filament\Support\Columns;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('published_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')->label(__('admin.fields.cover_image'))->disk('uploads')->imageHeight(36)->toggleable(),
                Columns::translated('title'),
                TextColumn::make('status')->label(__('admin.fields.status'))->badge(),
                TextColumn::make('published_at')->label(__('admin.fields.published_at'))->dateTime('d/m/Y H:i')->sortable(),
                Columns::missingTranslations(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('admin.fields.status'))->options(ArticleStatus::class),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
