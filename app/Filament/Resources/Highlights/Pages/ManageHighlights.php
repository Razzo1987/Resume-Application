<?php

namespace App\Filament\Resources\Highlights\Pages;

use App\Filament\Resources\Highlights\HighlightResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageHighlights extends ManageRecords
{
    protected static string $resource = HighlightResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
