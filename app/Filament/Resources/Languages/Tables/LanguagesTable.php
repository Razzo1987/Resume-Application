<?php

namespace App\Filament\Resources\Languages\Tables;

use App\Models\Language;
use App\Support\Languages\LanguagePacks;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class LanguagesTable
{
    public static function configure(Table $table): Table
    {
        $packs = app(LanguagePacks::class);

        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('code')->label(__('admin.fields.language_code'))->badge(),
                TextColumn::make('name')->label(__('admin.fields.language_name')),
                IconColumn::make('is_default')->label(__('admin.fields.is_default'))->boolean(),
                ToggleColumn::make('is_active')
                    ->label(__('admin.fields.is_active'))
                    ->disabled(fn (Language $record): bool => $record->is_default),
                TextColumn::make('segments')
                    ->label(__('admin.sections.url_segments'))
                    ->state(fn (Language $record): string => implode(' · ', $record->segments()))
                    ->color('gray'),
                TextColumn::make('pack')
                    ->label(__('admin.fields.language_pack'))
                    ->badge()
                    ->state(fn (Language $record): string => match (true) {
                        $packs->hasSiteStrings($record->code) => __('admin.labels.pack_complete'),
                        $packs->exists($record->code) => __('admin.labels.pack_urls_only'),
                        default => __('admin.labels.pack_custom'),
                    })
                    ->color(fn (Language $record): string => $packs->hasSiteStrings($record->code) ? 'success' : 'warning'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->hidden(fn (Language $record): bool => $record->is_default),
            ]);
    }
}
