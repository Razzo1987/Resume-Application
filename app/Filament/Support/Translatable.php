<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Languages\Locales;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Campi tradotti nei form admin: una scheda per ogni lingua attiva del sito.
 *
 *   Translatable::tabs(fn (string $locale, bool $isDefault) => [
 *       TextInput::make("title.{$locale}")->required($isDefault),
 *   ])
 *
 * La lingua di default è la prima scheda ed è l'unica con i campi obbligatori.
 */
class Translatable
{
    /**
     * @param  Closure(string $locale, bool $isDefault): array<Component|Field>  $fields
     */
    public static function tabs(Closure $fields, ?string $id = null): Tabs
    {
        $locales = app(Locales::class);
        $default = $locales->default();
        $tabs = [];

        foreach ($locales->active() as $code) {
            $tabs[] = Tab::make(self::label($code))
                ->key(($id ?? 'translations').'-'.$code)
                ->icon($code === $default ? 'heroicon-m-star' : null)
                ->schema($fields($code, $code === $default));
        }

        return Tabs::make($id ?? 'translations')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    public static function label(string $code): string
    {
        $language = app(Locales::class)->find($code);

        return ($language !== null ? $language->name : $code).' · '.$code;
    }
}
