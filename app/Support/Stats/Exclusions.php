<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatExclusion;
use App\Models\StatVisit;
use App\Support\Settings\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Addresses and networks whose visits are not counted.
 *
 * - auto: the public IP address an administrator uses for the admin. Visits from there are not counted for
 *   stats.admin_ip_days days after the last admin access (default 14): with a shared address (office, mobile network)
 *   the exclusion would also hide other people, that is why it expires on its own. Private addresses never become
 *   entries: a proxy can give everybody the same one (GitHub Codespaces); there AdminSession recognizes the
 *   administrator's browser.
 * - excluded: added by hand (IP address, network or ASN), until removed.
 * - always: always counted, also when used for the admin (a shared office network).
 *
 * Adding an exclusion deletes the visits already recorded: for an IP address those of the last 24 hours (the only
 * ones that can still be recognized, see Visitors), for a network or an ASN all those kept. Removing it starts the
 * count again from that moment, nothing is restored. When an administrator opens the admin, the visits of the last
 * 24 hours from the same browser are deleted too (those made just before signing in).
 */
class Exclusions
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Visitors $visitors,
    ) {}

    /**
     * Admin page with a signed-in administrator (at most once every 10 minutes per address and browser).
     */
    public function touchAdmin(?string $ip, string $userAgent): void
    {
        if ($ip === null || $ip === '' || ! $this->settings->bool('stats.enabled')) {
            return;
        }

        Cache::remember('resume.stats.admin.'.sha1($ip."\n".$userAgent), now()->addMinutes(10), function () use ($ip, $userAgent): bool {
            // the administrator's own visits of the last 24 hours, e.g. before signing in
            if ($userAgent !== '') {
                $this->deleteVisits($this->recent()->whereIn('visitor_hash', $this->visitors->visitorHashes($ip, $userAgent)));
            }

            if (! Ip::isPublic($ip)) {
                return true;
            }

            $entry = StatExclusion::query()->where('type', 'ip')->where('value', $ip)->first();

            if ($entry === null) {
                StatExclusion::query()->create(['type' => 'ip', 'value' => $ip, 'status' => StatExclusion::AUTO, 'last_seen_at' => now(), 'created_at' => now()]);
                $this->deleteVisits($this->matching('ip', $ip));
            } elseif ($entry->status === StatExclusion::AUTO) {
                $entry->update(['last_seen_at' => now()]);
            }

            return true;
        });
    }

    /**
     * True if the visits from this address (and network) are not counted.
     */
    public function isExcluded(string $ip, ?int $asn): bool
    {
        $network = Ip::truncate($ip);
        $entries = StatExclusion::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('type', 'ip')->where('value', $ip))
                ->orWhere(fn (Builder $query) => $query->where('type', 'network')->where('value', $network))
                ->when($asn !== null, fn (Builder $query) => $query->orWhere(fn (Builder $query) => $query->where('type', 'asn')->where('value', (string) $asn))))
            ->get()
            ->keyBy('type');

        $address = $entries->get('ip');
        $range = $entries->get('network');

        return match (true) {
            $address?->status === StatExclusion::ALWAYS => false,
            $address?->status === StatExclusion::EXCLUDED, $range?->status === StatExclusion::EXCLUDED => true,
            $entries->get('asn')?->status === StatExclusion::EXCLUDED => true,
            $range?->status === StatExclusion::ALWAYS => false,
            $address?->status === StatExclusion::AUTO => $address->last_seen_at !== null && $address->last_seen_at->gte(now()->subDays($this->days())) && Ip::isPublic($ip),
            default => false,
        };
    }

    /**
     * Adds an entry, or changes the status of the existing one. With "excluded" the visits already recorded go away.
     *
     * @return array{entry: StatExclusion, deleted: int}
     */
    public function add(string $type, string $value, string $status = StatExclusion::EXCLUDED, ?string $note = null): array
    {
        $value = self::normalize($type, $value) ?? throw new \InvalidArgumentException("Invalid {$type}: {$value}");

        $entry = StatExclusion::query()->firstOrNew(['type' => $type, 'value' => $value], ['created_at' => now()]);
        $entry->status = $status;

        if ($note !== null) {
            $entry->note = trim($note) !== '' ? mb_substr(trim($note), 0, 150) : null;
        }

        $entry->save();

        return ['entry' => $entry, 'deleted' => $status === StatExclusion::EXCLUDED ? $this->deleteVisits($this->matching($type, $value)) : 0];
    }

    /**
     * @return array{entry: StatExclusion, deleted: int}
     */
    public function setStatus(StatExclusion $entry, string $status): array
    {
        $entry->update(['status' => $status]);

        return ['entry' => $entry, 'deleted' => $status === StatExclusion::EXCLUDED ? $this->deleteVisits($this->matching($entry->type, $entry->value)) : 0];
    }

    /**
     * How many recorded visits an exclusion would delete (preview before adding it).
     */
    public function count(string $type, string $value): int
    {
        $value = self::normalize($type, $value);

        return $value !== null ? $this->matching($type, $value)->count() : 0;
    }

    /**
     * Visits that an exclusion covers: for an IP address the last 24 hours (by hash), otherwise all those kept.
     *
     * @return Builder<StatVisit>
     */
    public function matching(string $type, string $value): Builder
    {
        return match ($type) {
            'ip' => $this->recent()->whereIn('ip_hash', $this->visitors->ipHashes($value)),
            'network' => StatVisit::query()->where('ip', $value),
            'asn' => StatVisit::query()->where('asn', (int) $value),
            default => StatVisit::query()->whereRaw('0 = 1'),
        };
    }

    /**
     * Normalized value, or null if not valid: IP address as it is, network as shortened address ("93.44.12.x",
     * also from "93.44.12.0/24" or a full address), ASN as a number ("AS3269" → "3269").
     */
    public static function normalize(string $type, string $value): ?string
    {
        $value = trim($value);

        if ($type === 'ip') {
            return filter_var($value, FILTER_VALIDATE_IP) !== false ? $value : null;
        }

        if ($type === 'network') {
            $address = (string) preg_replace(['#/\d+$#', '/\.x$/i'], ['', '.0'], $value);

            return filter_var($address, FILTER_VALIDATE_IP) !== false ? Ip::truncate($address) : null;
        }

        if ($type === 'asn') {
            $number = (string) preg_replace('/^AS\s*/i', '', $value);

            return ctype_digit($number) && (int) $number > 0 ? (string) (int) $number : null;
        }

        return null;
    }

    public function days(): int
    {
        return max(1, (int) $this->settings->get('stats.admin_ip_days', 14));
    }

    /**
     * Removes the expired automatic entries.
     */
    public function prune(): int
    {
        return StatExclusion::query()
            ->where('status', StatExclusion::AUTO)
            ->where(fn (Builder $query) => $query->whereNull('last_seen_at')->orWhere('last_seen_at', '<', now()->subDays($this->days())))
            ->delete();
    }

    /**
     * Deletes visits and recalculates the daily totals of their days.
     *
     * @param  Builder<StatVisit>  $visits
     */
    public function deleteVisits(Builder $visits): int
    {
        $days = (clone $visits)->toBase()->distinct()->pluck('day');

        if ($days->isEmpty()) {
            return 0;
        }

        $deleted = $visits->delete();

        foreach ($days as $day) {
            app(Aggregator::class)->aggregate(Carbon::parse((string) $day));
        }

        return (int) $deleted;
    }

    /**
     * @return Builder<StatVisit>
     */
    private function recent(): Builder
    {
        return StatVisit::query()->where('visited_at', '>=', now()->subHours(Visitors::WINDOW_HOURS));
    }
}
