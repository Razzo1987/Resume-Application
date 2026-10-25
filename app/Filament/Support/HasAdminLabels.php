<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Illuminate\Support\Str;

/**
 * Etichette tradotte delle risorse admin (lang/{it,en}/admin.php → resources.{chiave}).
 * La risorsa definisce $labelKey e $navigationGroupKey ("cv", "content", "site").
 *
 * Le etichette sono scritte in minuscolo perché Filament le inserisce dentro le frasi
 * ("Modifica esperienza", "Elimina progetto"); dove stanno da sole (titoli, menu, briciole)
 * si usa solo l'iniziale maiuscola invece del "Title Case" all'inglese di Filament.
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
