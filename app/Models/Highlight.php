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
 * Key figure: value entered by hand or calculated (years of experience, number of projects, ...),
 * shown exact ("460") or with a plus ("460+").
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

    /** Calculated value (memoized: the automatic counters run a query). */
    private ?float $resolved = null;

    private bool $isResolved = false;

    /**
     * Numeric value: the one entered or calculated from the site content.
     */
    public function resolvedValue(): ?float
    {
        if ($this->isResolved) {
            return $this->resolved;
        }

        $this->isResolved = true;

        return $this->resolved = match ($this->source) {
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
     * Value formatted for the language, with "+" and unit: "100.000+", "4", "98%".
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
     * Text below the number: the one entered or, for automatic sources, the one from the language pack.
     */
    public function displayLabel(?string $locale = null): ?string
    {
        return $this->t('label', $locale)
            ?? ($this->source->isAutomatic() ? __('site.highlights.'.$this->source->value, locale: $locale) : null);
    }
}
