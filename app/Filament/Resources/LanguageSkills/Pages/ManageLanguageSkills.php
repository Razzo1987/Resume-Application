<?php

namespace App\Filament\Resources\LanguageSkills\Pages;

use App\Filament\Resources\LanguageSkills\LanguageSkillResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLanguageSkills extends ManageRecords
{
    protected static string $resource = LanguageSkillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
