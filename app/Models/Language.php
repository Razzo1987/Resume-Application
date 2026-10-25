<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Languages\LanguagePacks;
use App\Support\Languages\Locales;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Lingua del sito. Il codice (es. it-IT) è anche il prefisso degli URL.
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
            // La lingua di default è sempre attiva
            if ($language->is_default) {
                $language->is_active = true;
            }

            if ($language->exists && ! $language->is_default && (bool) $language->getRawOriginal('is_default')) {
                throw new LogicException('Scegli prima un\'altra lingua di default.');
            }
        });

        static::saved(function (Language $language): void {
            // Una sola lingua di default
            if ($language->is_default) {
                DB::table('languages')
                    ->where('id', '!=', $language->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            app(Locales::class)->flush();
        });

        static::deleting(function (Language $language): void {
            // Si controlla il valore salvato, non quello eventualmente modificato in memoria
            if ((bool) $language->getRawOriginal('is_default')) {
                throw new LogicException('La lingua di default non si può eliminare.');
            }
        });

        static::deleted(fn () => app(Locales::class)->flush());
    }

    /**
     * Segmento URL tradotto (es. "progetti"): override dell'admin, poi language pack, poi inglese.
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
     * Tutti i segmenti effettivi della lingua.
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
