<?php

namespace App\Filament\Resources\SocialLinks\Pages;

use App\Filament\Resources\SocialLinks\SocialLinkResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSocialLinks extends ManageRecords
{
    protected static string $resource = SocialLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
