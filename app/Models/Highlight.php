<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HighlightDisplay;
use App\Enums\HighlightSource;
use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use NumberFormatter;

/**
 * Numero chiave: valore inserito a mano o calcolato (anni di esperienza, n. progetti, ...),
 * mostrato esatto ("460") o con il più ("460+").
 *
 * @property int $id
 * @property HighlightSource $source
 * @property string|null $value
 * @property HighlightDisplay $display
 * @property string|null $unit
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_visible
 */
class Highlight extends Model implements Translatable
{
    use HasTranslations, HasVisibility, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['label'];

    protected $fillable = ['source', 'value', 'display', 'unit', 'label', 'icon', 'sort_order', 'is_visible'];

    protected $attributes = [
        'source' => 'manual',
        'display' => 'exact',
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'source' => HighlightSource::class,
            'display' => HighlightDisplay::class,
            'value' => 'decimal:2',
            'is_visible' => 'boolean',
        ];
    }

    /**
     * Valore numerico: quello inserito oppure calcolato dai contenuti del sito.
     */
    public function resolvedValue(): ?float
    {
        return match ($this->source) {
            HighlightSource::Manual => $this->value !== null ? (float) $this->value : null,
            HighlightSource::YearsExperience => ($start = Profile::current()->careerStart()) !== null
                ? (float) (int) $start->diffInYears(now())
                : null,
            HighlightSource::ProjectsCount => (float) Project::query()->visible()->count(),
            HighlightSource::CertificationsCount => (float) Certification::query()->visible()->count(),
            HighlightSource::PublicationsCount => (float) Publication::query()->visible()->count(),
            HighlightSource::ArticlesCount => (float) Article::query()->published()->count(),
        };
    }

    /**
     * Valore formattato secondo la lingua, con "+" e unità: "100.000+", "4", "98%".
     */
    public function formattedValue(?string $locale = null): ?string
    {
        $value = $this->resolvedValue();

        if ($value === null) {
            return null;
        }

        $formatter = new NumberFormatter(str_replace('-', '_', $locale ?? app()->getLocale()), NumberFormatter::DECIMAL);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, fmod($value, 1.0) === 0.0 ? 0 : 2);

        $number = (string) $formatter->format($value);

        return $number.($this->unit ?? '').($this->display === HighlightDisplay::Plus ? '+' : '');
    }

    /**
     * Testo sotto il numero: quello inserito o, per le fonti automatiche, quello del language pack.
     */
    public function displayLabel(?string $locale = null): ?string
    {
        return $this->t('label', $locale)
            ?? ($this->source->isAutomatic() ? __('site.highlights.'.$this->source->value, locale: $locale) : null);
    }
}
