<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CertificationType;
use App\Models\Concerns\HasVisibility;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $organization_id
 * @property CertificationType $type
 * @property Carbon|null $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $credential_id
 * @property string|null $verification_url
 * @property string|null $file
 * @property string|null $badge
 * @property bool $is_visible
 */
class Certification extends Model implements Translatable
{
    use HasTranslations, HasVisibility;

    /** @var list<string> */
    protected array $translatable = ['title'];

    protected $fillable = [
        'organization_id', 'title', 'type', 'issued_on', 'expires_on', 'credential_id',
        'verification_url', 'file', 'badge', 'is_visible',
    ];

    protected $attributes = [
        'type' => 'certification',
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => CertificationType::class,
            'issued_on' => 'date',
            'expires_on' => 'date',
            'is_visible' => 'boolean',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('issued_on')->orderByDesc('id');
    }
}
