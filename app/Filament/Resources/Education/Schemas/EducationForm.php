<?php

namespace App\Filament\Resources\Education\Schemas;

use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EducationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->schema([
                    Select::make('organization_id')
                        ->label(__('admin.fields.institution'))
                        ->relationship('organization', 'name')
                        ->searchable()
                        ->preload()
                        ->createOptionForm(OrganizationForm::fields())
                        ->createOptionUsing(fn (array $data): int => Organization::query()->create($data)->getKey()),
                    Fields::period(startRequired: false),
                    Grid::make(3)->schema([
                        TextInput::make('grade')->label(__('admin.fields.grade'))->maxLength(20)->placeholder('110'),
                        TextInput::make('grade_scale')->label(__('admin.fields.grade_scale'))->maxLength(20)->placeholder('110'),
                        Toggle::make('cum_laude')->label(__('admin.fields.cum_laude'))->inline(false),
                    ]),
                    TextInput::make('link')->label(__('admin.fields.link'))->url()->maxLength(500),
                    Fields::visible(),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        Grid::make(2)->schema([
                            TextInput::make("degree.{$locale}")->label(__('admin.fields.degree'))->required($isDefault)->maxLength(255),
                            TextInput::make("field_of_study.{$locale}")->label(__('admin.fields.field_of_study'))->maxLength(255),
                        ]),
                        TextInput::make("thesis_title.{$locale}")->label(__('admin.fields.thesis_title'))->maxLength(255),
                        Fields::richSummary("summary.{$locale}")
                            ->label(__('admin.fields.summary')),
                        Fields::richText("details.{$locale}", 'education/content')
                            ->label(__('admin.fields.details'))
                            ->helperText(__('admin.help.education_details')),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }
}
