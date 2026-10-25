<?php

namespace App\Filament\Resources\Publications;

use App\Filament\Resources\Publications\Pages\CreatePublication;
use App\Filament\Resources\Publications\Pages\EditPublication;
use App\Filament\Resources\Publications\Pages\ListPublications;
use App\Filament\Resources\Publications\Schemas\PublicationForm;
use App\Filament\Resources\Publications\Tables\PublicationsTable;
use App\Filament\Support\HasAdminLabels;
use App\Models\Publication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PublicationResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = Publication::class;

    protected static string $labelKey = 'publications';

    protected static string $navigationGroupKey = 'cv';

    protected static ?int $navigationSort = 80;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    public static function form(Schema $schema): Schema
    {
        return PublicationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublications::route('/'),
            'create' => CreatePublication::route('/create'),
            'edit' => EditPublication::route('/{record}/edit'),
        ];
    }
}
