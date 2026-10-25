<?php

namespace App\Filament\Resources\Redirects;

use App\Filament\Resources\Redirects\Pages\ManageRedirects;
use App\Filament\Support\HasAdminLabels;
use App\Models\Redirect;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class RedirectResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Redirect::class;

    protected static string $labelKey = 'redirects';

    protected static string $navigationGroupKey = 'site';

    protected static ?int $navigationSort = 40;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('from_path')
                ->label(__('admin.fields.from_path'))
                ->helperText(__('admin.help.from_path'))
                ->required()
                ->maxLength(500)
                ->unique(ignoreRecord: true),
            TextInput::make('to_path')->label(__('admin.fields.to_path'))->helperText(__('admin.help.to_path'))->required()->maxLength(500),
            Select::make('status_code')
                ->label(__('admin.fields.status_code'))
                ->options([301 => __('admin.labels.redirect_301'), 302 => __('admin.labels.redirect_302')])
                ->default(301)
                ->required(),
            Toggle::make('is_active')->label(__('admin.fields.is_active'))->default(true),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('from_path')
            ->columns([
                TextColumn::make('from_path')->label(__('admin.fields.from_path'))->searchable(),
                TextColumn::make('to_path')->label(__('admin.fields.to_path'))->searchable(),
                TextColumn::make('status_code')->label(__('admin.fields.status_code'))->badge(),
                TextColumn::make('hits')->label(__('admin.fields.hits'))->sortable(),
                ToggleColumn::make('is_active')->label(__('admin.fields.is_active')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRedirects::route('/'),
        ];
    }
}
