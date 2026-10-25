<?php

namespace App\Filament\Resources\Interests\Pages;

use App\Filament\Resources\Interests\InterestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageInterests extends ManageRecords
{
    protected static string $resource = InterestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
