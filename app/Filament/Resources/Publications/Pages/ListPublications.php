<?php

namespace App\Filament\Resources\Publications\Pages;

use App\Filament\Resources\Publications\PublicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublications extends ListRecords
{
    protected static string $resource = PublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
