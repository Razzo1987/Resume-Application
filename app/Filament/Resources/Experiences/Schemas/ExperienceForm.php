<?php

namespace App\Filament\Resources\Experiences\Schemas;

use App\Enums\ExperienceKind;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Models\Organization;
use App\Models\Skill;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExperienceForm
{
    public static function configure(Schema $schema, ExperienceKind $kind): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->schema([
                    Hidden::make('kind')->default($kind->value),
                    Select::make('organization_id')
                        ->label(__('admin.fields.organization'))
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm(OrganizationForm::fields())
                        ->createOptionUsing(fn (array $data): int => Organization::query()->create($data)->getKey()),
                    Fields::period(),
                    Select::make('skills')
                        ->label(__('admin.fields.skills'))
                        ->relationship('skills', 'id')
                        ->getOptionLabelFromRecordUsing(fn (Skill $record): string => (string) $record->t('name'))
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Fields::visible(),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        TextInput::make("role.{$locale}")
                            ->label(__('admin.fields.role'))
                            ->required($isDefault)
                            ->maxLength(255),
                        TextInput::make("location.{$locale}")
                            ->label(__('admin.fields.location'))
                            ->maxLength(255),
                        Fields::richSummary("summary.{$locale}")
                            ->label(__('admin.fields.summary'))
                            ->helperText(__('admin.help.summary')),
                        Fields::richText("details.{$locale}", 'experiences/content')
                            ->label(__('admin.fields.details'))
                            ->helperText(__('admin.help.details')),
                        Fieldset::make(__('admin.sections.seo'))->schema([
                            TextInput::make("slug.{$locale}")
                                ->label(__('admin.fields.slug'))
                                ->helperText(__('admin.help.slug'))
                                ->maxLength(150),
                            TextInput::make("seo_title.{$locale}")
                                ->label(__('admin.fields.seo_title'))
                                ->maxLength(70),
                            Textarea::make("seo_description.{$locale}")
                                ->label(__('admin.fields.seo_description'))
                                ->maxLength(170)
                                ->rows(2)
                                ->columnSpanFull(),
                        ]),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
