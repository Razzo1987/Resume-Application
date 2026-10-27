<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Support\Languages\Locales;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Translated fields in the admin forms: one tab for each active language of the site.
 *
 *   Translatable::tabs(fn (string $locale, bool $isDefault) => [
 *       TextInput::make("title.{$locale}")->required($isDefault),
 *   ])
 *
 * The default language is the first tab and the only one with required fields.
 */
class Translatable
{
    /**
     * @param  Closure(string $locale, bool $isDefault): array<Component|Field>  $fields
     * @param  (Closure(string $locale): list<string>)|null  $missing  fields still to translate in a language: the tab
     *                                                                 shows how many (a badge), with their names on hover
     */
    public static function tabs(Closure $fields, ?string $id = null, ?Closure $missing = null): Tabs
    {
        $locales = app(Locales::class);
        $default = $locales->default();
        $tabs = [];

        foreach ($locales->active() as $code) {
            $tab = Tab::make(self::label($code))
                ->key(($id ?? 'translations').'-'.$code)
                ->icon($code === $default ? 'heroicon-m-star' : null)
                ->schema($fields($code, $code === $default));

            if ($missing !== null) {
                $tab->badge(fn (): ?string => ($count = count($missing($code))) > 0 ? (string) $count : null)
                    ->badgeColor('warning')
                    ->badgeTooltip(fn (): string => __('admin.translations.missing_fields', ['fields' => implode(', ', array_map(self::fieldLabel(...), $missing($code)))]));
            }

            $tabs[] = $tab;
        }

        return Tabs::make($id ?? 'translations')
            ->tabs($tabs)
            ->columnSpanFull();
    }

    /**
     * Name of a translatable field for the admin ("bio" => "Presentazione").
     */
    public static function fieldLabel(string $field): string
    {
        $key = match ($field) {
            'photo_alt', 'cover_alt' => 'image_alt',
            'seo.title' => 'seo_title',
            'seo.description' => 'seo_description',
            'footer.gdpr_text' => 'gdpr_text',
            'privacy.text' => 'privacy_text',
            default => $field,
        };

        return Lang::has("admin.fields.{$key}") ? (string) __("admin.fields.{$key}") : Str::headline($field);
    }

    public static function label(string $code): string
    {
        $language = app(Locales::class)->find($code);

        return ($language !== null ? $language->name : $code).' · '.$code;
    }
}
