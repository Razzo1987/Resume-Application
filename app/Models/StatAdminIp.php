<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * IP da cui un amministratore ha usato l'admin: le visite al sito da lì non contano. Vedi App\Support\Stats\AdminIps.
 *
 * @property int $id
 * @property string $ip
 * @property Carbon $last_seen_at
 */
class StatAdminIp extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime'];
    }
}
