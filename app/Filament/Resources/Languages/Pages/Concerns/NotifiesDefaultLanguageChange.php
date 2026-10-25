<?php

declare(strict_types=1);

namespace App\Filament\Resources\Languages\Pages\Concerns;

use App\Models\Language;
use Filament\Notifications\Notification;

/**
 * Dopo il cambio della lingua di default ricorda che il redirect di "/" resta nella cache dei browser per un giorno.
 */
trait NotifiesDefaultLanguageChange
{
    protected function notifyDefaultLanguageChange(Language $language, bool $changed): void
    {
        if (! $changed || ! $language->is_default) {
            return;
        }

        Notification::make()
            ->warning()
            ->title(__('admin.messages.default_language_changed', ['language' => $language->name]))
            ->body(__('admin.help.default_language_cache'))
            ->persistent()
            ->send();
    }
}
