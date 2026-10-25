<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsChronological;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property string|null $grade
 * @property string|null $grade_scale
 * @property bool $cum_laude
 * @property string|null $link
 * @property bool $is_visible
 */
class Education extends Model implements Translatable
{
    use HasTranslations, HasVisibility, IsChronological;

    protected $table = 'educations';

    /** @var list<string> */
    protected array $translatable = ['degree', 'field_of_study', 'thesis_title', 'summary', 'details'];

    protected $fillable = [
        'organization_id', 'degree', 'field_of_study', 'start_date', 'end_date', 'grade', 'grade_scale',
        'cum_laude', 'thesis_title', 'summary', 'details', 'link', 'is_visible',
    ];

    protected $attributes = [
        'cum_laude' => false,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'cum_laude' => 'boolean',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
