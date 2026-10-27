<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatAdminIp;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Administrator IPs: whoever uses the admin from an address is the administrator, so site visits from there are
 * not counted, for stats.admin_ip_days days after the last admin access (default 14). With a shared IP (office,
 * mobile network) the exclusion would also affect other people: that is why it expires on its own.
 */
class AdminIps
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Records the IP of an admin page (at most one write every 10 minutes per address).
     */
    public function touch(?string $ip): void
    {
        if ($ip === null || $ip === '' || ! $this->settings->bool('stats.enabled')) {
            return;
        }

        Cache::remember('resume.stats.admin_ip.'.sha1($ip), now()->addMinutes(10), function () use ($ip): bool {
            StatAdminIp::query()->updateOrCreate(['ip' => $ip], ['last_seen_at' => now()]);

            return true;
        });
    }

    public function isExcluded(string $ip): bool
    {
        return StatAdminIp::query()
            ->where('ip', $ip)
            ->where('last_seen_at', '>=', now()->subDays($this->days()))
            ->exists();
    }

    public function days(): int
    {
        return max(1, (int) $this->settings->get('stats.admin_ip_days', 14));
    }

    /**
     * Removes the expired addresses.
     */
    public function prune(): int
    {
        return StatAdminIp::query()->where('last_seen_at', '<', now()->subDays($this->days()))->delete();
    }
}
