<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Site language. The code (e.g. it-IT) is also the URL prefix.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $is_default
 * @property bool $is_active
 * @property int $sort_order
 * @property array<string, string>|null $url_segments
 * @property array<string, string>|null $string_overrides
 */
class Language extends Model
{
    protected $fillable = [
        'code',
        'name',
        'is_default',
        'is_active',
        'sort_order',
        'url_segments',
        'string_overrides',
    ];

    protected $attributes = [
        'is_default' => false,
        'is_active' => true,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'url_segments' => 'array',
            'string_overrides' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Language $language): void {
            // The default language is always active
            if ($language->is_default) {
                $language->is_active = true;
            }

            if ($language->exists && ! $language->is_default && (bool) $language->getRawOriginal('is_default')) {
                throw new LogicException('Scegli prima un\'altra lingua di default.');
            }
        });

        static::saved(function (Language $language): void {
            // Only one default language
            if ($language->is_default) {
                DB::table('languages')
                    ->where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            app(Locales::class)->flush();
        });

        static::deleting(function (Language $language): void {
            // Check the stored value, not the one possibly changed in memory
            if ((bool) $language->getRawOriginal('is_default')) {
                throw new LogicException('La lingua di default non si può eliminare.');
            }
        });

        static::deleted(fn () => app(Locales::class)->flush());
    }

    /**
     * Translated URL segment (e.g. "progetti"): admin override, then language pack, then English.
     */
    public function segment(string $key): string
    {
        $packs = app(LanguagePacks::class);

        return $this->url_segments[$key]
            ?? $packs->segments($this->code)[$key]
            ?? $packs->segments('en-GB')[$key]
            ?? $key;
    }

    /**
     * All the effective segments of the language.
     *
     * @return array<string, string>
     */
    public function segments(): array
    {
        $segments = [];

        foreach (LanguagePacks::SEGMENTS as $key) {
            $segments[$key] = $this->segment($key);
        }

        return $segments;
    }
}
