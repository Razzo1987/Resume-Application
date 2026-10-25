<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Enums\OrganizationType;
use App\Filament\Support\Fields;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationForm
{
    /**
     * Campi riusati anche per creare un'organizzazione al volo dai form di esperienze, progetti, ecc.
     *
     * @return array<Field>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('name')
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label(__('admin.fields.organization_type'))
                ->options(OrganizationType::class)
                ->default(OrganizationType::Company->value)
                ->required(),
            TextInput::make('website')
                ->label(__('admin.fields.website'))
                ->url()
                ->maxLength(500),
            Fields::image('logo', 'logos', 600)
                ->imageEditor(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->schema(self::fields())->columns(2)->columnSpanFull(),
        ]);
    }
}
