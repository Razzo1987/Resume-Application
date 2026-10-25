<?php

namespace App\Filament\Resources\Volunteering\Pages;

use App\Filament\Resources\Volunteering\VolunteeringResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVolunteering extends EditRecord
{
    protected static string $resource = VolunteeringResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
