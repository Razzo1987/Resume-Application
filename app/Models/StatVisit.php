<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One page view (or one CV download), without cookies and with the IP truncated. See App\Support\Stats\Tracker.
 *
 * @property int $id
 * @property Carbon $visited_at
 * @property Carbon $day
 * @property string $page
 * @property string $path
 * @property string $locale
 * @property string|null $referrer_host
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string|null $click_source
 * @property string|null $device
 * @property string|null $os
 * @property string|null $browser
 * @property string|null $browser_version
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property float|null $latitude
 * @property float|null $longitude
 * @property int|null $asn
 * @property string|null $as_org
 * @property string $ip
 */
class StatVisit extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'visited_at' => 'datetime',
            'day' => 'date',
            'latitude' => 'float',
            'longitude' => 'float',
            'asn' => 'integer',
        ];
    }
}
