<?php

namespace App\Filament\Resources\Articles\Schemas;

use App\Enums\ArticleStatus;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.publishing'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('status')->label(__('admin.fields.status'))->options(ArticleStatus::class)->default(ArticleStatus::Draft->value)->required(),
                        DateTimePicker::make('published_at')
                            ->label(__('admin.fields.published_at'))
                            ->helperText(__('admin.help.published_at'))
                            ->native(false)
                            ->seconds(false),
                    ]),
                    Fields::image('cover_image', 'articles', 2000),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        TextInput::make("title.{$locale}")->label(__('admin.fields.title'))->required($isDefault)->maxLength(255),
                        TextInput::make("slug.{$locale}")->label(__('admin.fields.slug'))->helperText(__('admin.help.slug'))->maxLength(150),
                        Textarea::make("excerpt.{$locale}")->label(__('admin.fields.excerpt'))->helperText(__('admin.help.excerpt'))->rows(3)->maxLength(300),
                        Fields::richText("body.{$locale}", 'articles/content')
                            ->label(__('admin.fields.body')),
                        TextInput::make("cover_alt.{$locale}")->label(__('admin.fields.image_alt'))->maxLength(255),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
