<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Colonna is_visible: gli elementi nascosti restano in admin ma non compaiono sul sito.
 *
 * @phpstan-require-extends Model
 */
trait HasVisibility
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('is_visible'), true);
    }
}
