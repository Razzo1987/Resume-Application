<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialPlatform;
use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsOrdered;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property SocialPlatform $platform
 * @property string $url
 * @property string|null $label
 * @property int $sort_order
 * @property bool $is_visible
 */
class SocialLink extends Model
{
    use HasVisibility, IsOrdered;

    protected $fillable = ['platform', 'url', 'label', 'sort_order', 'is_visible'];

    protected function casts(): array
    {
        return [
            'platform' => SocialPlatform::class,
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
