<?php

namespace App\Filament\Resources\CvDocuments\Pages;

use App\Filament\Resources\CvDocuments\CvDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCvDocuments extends ListRecords
{
    protected static string $resource = CvDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
