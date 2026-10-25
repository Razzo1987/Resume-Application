<?php

namespace App\Filament\Resources\CvDocuments\Pages;

use App\Filament\Resources\CvDocuments\CvDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCvDocument extends EditRecord
{
    protected static string $resource = CvDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
