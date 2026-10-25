<?php

namespace App\Filament\Resources\Experiences\Pages;

use App\Filament\Resources\Experiences\ExperienceResource;
use App\Filament\Support\SiteActions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExperience extends EditRecord
{
    protected static string $resource = ExperienceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SiteActions::viewOnSite(),
            SiteActions::moveExperience(),
            DeleteAction::make(),
        ];
    }
}
