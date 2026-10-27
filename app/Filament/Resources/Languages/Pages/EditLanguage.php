<?php

namespace App\Filament\Resources\Languages\Pages;

use App\Filament\Resources\Languages\LanguageResource;
use App\Filament\Resources\Languages\Pages\Concerns\NotifiesDefaultLanguageChange;
use App\Models\Language;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLanguage extends EditRecord
{
    use NotifiesDefaultLanguageChange;

    protected static string $resource = LanguageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // The default language cannot be deleted: another one must be chosen first
            DeleteAction::make()->hidden(fn (Language $record): bool => $record->is_default),
        ];
    }

    protected function afterSave(): void
    {
        /** @var Language $language */
        $language = $this->getRecord();

        $this->notifyDefaultLanguageChange($language, $language->wasChanged('is_default'));
    }
}
