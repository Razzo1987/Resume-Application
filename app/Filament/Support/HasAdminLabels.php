<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * Translated labels of the admin resources (lang/{it,en}/admin.php → resources.{key}).
 * The resource defines $labelKey and $navigationGroupKey ("cv", "content", "site").
 *
 * Labels are written in lowercase because Filament inserts them into sentences
 * ("Modifica esperienza", "Elimina progetto"); where they stand alone (titles, menus, breadcrumbs)
 * only the first letter is capitalized, instead of Filament's English-style "Title Case".
 */
trait HasAdminLabels
{
    public static function getModelLabel(): string
    {
        return __('admin.resources.'.static::$labelKey.'.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('admin.resources.'.static::$labelKey.'.plural');
    }

    public static function getTitleCaseModelLabel(): string
    {
        return static::getModelLabel();
    }

    public static function getTitleCasePluralModelLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function getNavigationLabel(): string
    {
        return Str::ucfirst(static::getPluralModelLabel());
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.'.static::$navigationGroupKey);
    }
}
