<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasVisibility;
use App\Models\Concerns\IsOrdered;
use App\Support\Translatable\HasTranslations;
use App\Support\Translatable\Translatable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_visible
 */
class Interest extends Model implements Translatable
{
    use HasTranslations, HasVisibility, IsOrdered;

    /** @var list<string> */
    protected array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'description', 'icon', 'sort_order', 'is_visible'];

    protected $attributes = [
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
        ];
    }
}
