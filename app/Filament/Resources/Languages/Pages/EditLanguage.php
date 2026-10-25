<?php

namespace App\Filament\Resources\Languages\Pages;

use App\Filament\Resources\Languages\LanguageResource;
use App\Models\Language;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLanguage extends EditRecord
{
    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // La lingua di default non si può eliminare: prima se ne sceglie un'altra
            DeleteAction::make()->hidden(fn (Language $record): bool => $record->is_default),
        ];
    }
}
