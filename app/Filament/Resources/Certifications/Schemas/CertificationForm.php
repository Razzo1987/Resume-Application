<?php

namespace App\Filament\Resources\Certifications\Schemas;

use App\Enums\CertificationType;
use App\Enums\OrganizationType;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Support\Fields;
use App\Filament\Support\Translatable;
use App\Models\Organization;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CertificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('admin.sections.content'))
                ->schema([
                    Translatable::tabs(fn (string $locale, bool $isDefault): array => [
                        TextInput::make("title.{$locale}")->label(__('admin.fields.title'))->required($isDefault)->maxLength(255),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make(__('admin.sections.general'))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('organization_id')
                            ->label(__('admin.fields.issuer'))
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->createOptionForm(OrganizationForm::fields())
                            ->createOptionUsing(fn (array $data): int => Organization::query()->create($data + ['type' => OrganizationType::Certifier->value])->getKey()),
                        Select::make('type')
                            ->label(__('admin.fields.certification_type'))
                            ->options(CertificationType::class)
                            ->default(CertificationType::Certification->value)
                            ->required(),
                        DatePicker::make('issued_on')->label(__('admin.fields.issued_on'))->native(false),
                        DatePicker::make('expires_on')->label(__('admin.fields.expires_on'))->native(false)->helperText(__('admin.help.expires_empty')),
                        TextInput::make('credential_id')->label(__('admin.fields.credential_id'))->maxLength(255),
                        TextInput::make('verification_url')->label(__('admin.fields.verification_url'))->url()->maxLength(500),
                    ]),
                    Grid::make(2)->schema([
                        Fields::image('badge', 'badges', 480),
                        Fields::publicPdf('file', 'certificates'),
                    ]),
                    Fields::visible(),
                ])
                ->columnSpanFull(),
        ]);
    }
}
