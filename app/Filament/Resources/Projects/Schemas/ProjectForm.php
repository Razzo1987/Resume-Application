<?php

namespace App\Filament\Resources\Projects\Schemas;

use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Models\Experience;
use App\Models\Organization;
use App\Models\Skill;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ProjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.general'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('experience_id')
                            ->label(__('admin.fields.experience'))
                            ->helperText(__('admin.help.project_experience'))
                            ->relationship('experience', 'id', self::experienceQuery(...))
                            ->getOptionLabelFromRecordUsing(fn (Experience $record): string => trim($record->t('role').' · '.($record->organization->name ?? ''), ' ·'))
                            ->searchable()
                            ->preload(),
                        Select::make('client_id')
                            ->label(__('admin.fields.client'))
                            ->relationship('client', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(OrganizationForm::fields())
                            ->createOptionUsing(fn (array $data): int => Organization::query()->create($data)->getKey()),
                    ]),
                    Fields::period(),
                    Select::make('skills')
                        ->label(__('admin.fields.skills'))
                        ->relationship('skills', 'id')
                        ->getOptionLabelFromRecordUsing(fn (Skill $record): string => (string) $record->t('name'))
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Fields::image('image', 'projects'),
                    Grid::make(2)->schema([
                        Toggle::make('is_featured')->label(__('admin.fields.is_featured')),
                        Fields::visible(),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        TextInput::make("title.{$locale}")
                            ->label(__('admin.fields.title'))
                            ->required($isDefault)
                            ->maxLength(255),
                        Grid::make(2)->schema([
                            TextInput::make("role.{$locale}")->label(__('admin.fields.role'))->maxLength(255),
                            TextInput::make("sector.{$locale}")->label(__('admin.fields.sector'))->maxLength(255),
                        ]),
                        Fields::richSummary("summary.{$locale}")
                            ->label(__('admin.fields.summary'))
                            ->helperText(__('admin.help.summary')),
                        Fields::richText("details.{$locale}", 'projects/content')
                            ->label(__('admin.fields.details'))
                            ->helperText(__('admin.help.details')),
                        TextInput::make("image_alt.{$locale}")->label(__('admin.fields.image_alt'))->maxLength(255),
                        Fieldset::make(__('admin.sections.seo'))->schema([
                            TextInput::make("slug.{$locale}")->label(__('admin.fields.slug'))->helperText(__('admin.help.slug'))->maxLength(150),
                            Fields::counted(TextInput::make("seo_title.{$locale}")->label(__('admin.fields.seo_title'))->maxLength(120), Fields::SEO_TITLE_LENGTH),
                            Fields::counted(Textarea::make("seo_description.{$locale}")->label(__('admin.fields.seo_description'))->helperText(__('admin.help.seo_description_length'))->maxLength(300)->rows(2), Fields::SEO_DESCRIPTION_LENGTH)->columnSpanFull(),
                        ]),
                    ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    /**
     * @param  Builder<Experience>  $query
     * @return Builder<Experience>
     */
    private static function experienceQuery(Builder $query): Builder
    {
        return $query->with('organization')->chronological();
    }
}
