<?php

namespace App\Filament\Resources\Languages\Pages;

use App\Filament\Resources\Languages\LanguageResource;
use App\Filament\Resources\Languages\Pages\Concerns\NotifiesDefaultLanguageChange;
use App\Models\Language;
use Filament\Resources\Pages\CreateRecord;

class CreateLanguage extends CreateRecord
{
    use NotifiesDefaultLanguageChange;

    protected static string $resource = LanguageResource::class;

    protected function afterCreate(): void
    {
        /** @var Language $language */
        $language = $this->getRecord();

        $this->notifyDefaultLanguageChange($language, true);
    }
}
