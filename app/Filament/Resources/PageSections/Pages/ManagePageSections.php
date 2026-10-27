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
        // New sections introduced by updates show up on their own
        PageSection::ensureDefaults();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
