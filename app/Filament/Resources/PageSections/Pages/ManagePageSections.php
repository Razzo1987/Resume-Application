<?php

namespace App\Filament\Resources\PageSections\Pages;

use App\Filament\Resources\PageSections\PageSectionResource;
use App\Models\PageSection;
use Filament\Resources\Pages\ManageRecords;

class ManagePageSections extends ManageRecords
{
    protected static string $resource = PageSectionResource::class;

    public function mount(): void
    {
        // Le sezioni nuove introdotte dagli aggiornamenti compaiono da sole
        PageSection::ensureDefaults();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
