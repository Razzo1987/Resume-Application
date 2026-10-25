<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CefrLevel;
use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Locale;

/**
 * Lingua conosciuta, con livelli QCER (A1–C2) per orale e scritto e comprensione facoltativa.
 *
 * @property int $id
 * @property string $language_code
 * @property bool $is_native
 * @property CefrLevel|null $oral
 * @property CefrLevel|null $written
 * @property CefrLevel|null $comprehension
 * @property int|null $certification_id
 * @property int $sort_order
 * @property bool $is_visible
 */
class LanguageSkill extends Model implements Translatable
{
    use HasTranslations, HasVisibility, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['note'];

    protected $fillable = [
        'language_code', 'is_native', 'oral', 'written', 'comprehension',
        'certification_id', 'note', 'sort_order', 'is_visible',
    ];

    protected $attributes = [
        'is_native' => false,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_native' => 'boolean',
            'oral' => CefrLevel::class,
            'written' => CefrLevel::class,
            'comprehension' => CefrLevel::class,
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Certification, $this> */
    public function certification(): BelongsTo
    {
        return $this->belongsTo(Certification::class);
    }

    /**
     * Nome della lingua nella lingua indicata (da intl), es. "inglese" o "English".
     */
    public function displayName(?string $inLocale = null): string
    {
        $name = Locale::getDisplayLanguage($this->language_code, $inLocale ?? app()->getLocale());

        return $name !== false ? mb_strtoupper(mb_substr($name, 0, 1)).mb_substr($name, 1) : $this->language_code;
    }
}
