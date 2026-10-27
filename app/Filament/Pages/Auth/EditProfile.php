<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Support\Languages\AdminLocales;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;

/**
 * Administrator profile: name, email, password and admin language.
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
     * Language changed: the page is reloaded, so the whole admin is in the new language right away.
     */
    protected function afterSave(): void
    {
        if ($this->getUser()->getAttribute('locale') !== $this->previousLocale) {
            $this->redirect(static::getUrl());
        }
    }
}
