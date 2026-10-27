<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Total of a day per page and dimension (e.g. day, "project:3", "os", "Android", 12). See App\Support\Stats\Aggregator.
 *
 * @property int $id
 * @property Carbon $day
 * @property string $page
 * @property string $dimension
 * @property string $value
 * @property int $views
 * @property float|null $latitude
 * @property float|null $longitude
 */
class StatDaily extends Model
{
    public $timestamps = false;

    protected $table = 'stat_daily';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'day' => 'date',
            'views' => 'integer',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }
}
