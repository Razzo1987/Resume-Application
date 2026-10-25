<?php

namespace App\Filament\Resources\CvDocuments;

use App\Filament\Resources\CvDocuments\Pages\CreateCvDocument;
use App\Filament\Resources\CvDocuments\Pages\EditCvDocument;
use App\Filament\Resources\CvDocuments\Pages\ListCvDocuments;
use App\Filament\Resources\CvDocuments\Schemas\CvDocumentForm;
use App\Filament\Resources\CvDocuments\Tables\CvDocumentsTable;
use App\Filament\Support\HasAdminLabels;
use App\Models\CvDocument;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CvDocumentResource extends Resource
{
    use HasAdminLabels;

    protected static ?string $model = CvDocument::class;

    protected static string $labelKey = 'cv_documents';

    protected static string $navigationGroupKey = 'content';

    protected static ?int $navigationSort = 20;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowDown;

    public static function form(Schema $schema): Schema
    {
        return CvDocumentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CvDocumentsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCvDocuments::route('/'),
            'create' => CreateCvDocument::route('/create'),
            'edit' => EditCvDocument::route('/{record}/edit'),
        ];
    }
}
