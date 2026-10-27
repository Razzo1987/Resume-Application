<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Support\Languages\AdminLocales;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Profilo dell'amministratore: nome, email, password e lingua dell'admin.
 */
class EditProfile extends BaseEditProfile
{
    private ?string $previousLocale = null;

    public function form(Schema $schema): Schema
    {
        $locales = app(AdminLocales::class);

        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            Select::make('locale')
                ->label(__('admin.fields.admin_locale'))
                ->helperText(__('admin.help.admin_locale'))
                ->options($locales->available())
                ->placeholder(__('admin.fields.admin_locale_default', ['language' => $locales->available()[$locales->default()] ?? $locales->default()])),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }

    protected function beforeSave(): void
    {
        $this->previousLocale = $this->getUser()->getAttribute('locale');
    }

    /**
     * Lingua cambiata: si ricarica la pagina, così tutto l'admin è subito nella lingua nuova.
     */
    protected function afterSave(): void
    {
        if ($this->getUser()->getAttribute('locale') !== $this->previousLocale) {
            $this->redirect(static::getUrl());
        }
    }
}
