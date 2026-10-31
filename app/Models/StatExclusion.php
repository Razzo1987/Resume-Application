<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Entry of the statistics exclusions: an IP address, a network (shortened address, as shown in the visits) or an
 * ASN, with its status. See App\Support\Stats\Exclusions.
 *
 * @property int $id
 * @property string $type ip, network, asn
 * @property string $value
 * @property string $status auto (address used for the admin), excluded, always (always counted)
 * @property string|null $note
 * @property Carbon|null $last_seen_at
 * @property Carbon $created_at
 */
class StatExclusion extends Model
{
    public const string AUTO = 'auto';

    public const string EXCLUDED = 'excluded';

    public const string ALWAYS = 'always';

    public const array TYPES = ['ip', 'network', 'asn'];

    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_seen_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
