<?php

namespace App\Filament\Resources\SocialLinks;

use App\Enums\SocialPlatform;
use App\Filament\Resources\SocialLinks\Pages\ManageSocialLinks;
use App\Filament\Support\Fields;
use App\Filament\Support\HasAdminLabels;
use App\Models\SocialLink;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class SocialLinkResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = SocialLink::class;

    protected static string $labelKey = 'social_links';

    protected static string $navigationGroupKey = 'site';

    protected static ?int $navigationSort = 30;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShare;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('platform')->label(__('admin.fields.platform'))->options(SocialPlatform::class)->required(),
            TextInput::make('url')->label(__('admin.fields.url'))->url()->required()->maxLength(500),
            TextInput::make('label')->label(__('admin.fields.label'))->helperText(__('admin.help.social_label'))->maxLength(255),
            Fields::visible(),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('platform')->label(__('admin.fields.platform'))->badge(),
                TextColumn::make('url')->label(__('admin.fields.url'))->limit(60),
                ToggleColumn::make('is_visible')->label(__('admin.fields.is_visible')),
            ])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSocialLinks::route('/'),
        ];
    }
}
