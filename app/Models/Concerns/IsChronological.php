<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Elementi con periodo (start_date / end_date): ordine dal più recente, quelli in corso per primi.
 *
 * @phpstan-require-extends Model
 */
trait IsChronological
{
    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeChronological(Builder $query): Builder
    {
        $end = $this->qualifyColumn('end_date');

        return $query
            ->orderByRaw("{$end} IS NULL DESC")
            ->orderByDesc($end)
            ->orderByDesc($this->qualifyColumn('start_date'));
    }

    public function isOngoing(): bool
    {
        return $this->getAttribute('end_date') === null;
    }
}
