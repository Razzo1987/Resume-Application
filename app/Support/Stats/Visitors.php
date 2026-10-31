<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatVisit;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Visitors and sessions without cookies, for at most 24 hours.
 *
 * Each visit stores two keyed hashes (HMAC-SHA256 with a random salt): one of the IP address (used by the
 * exclusions, where the browser is not known) and one of IP address + user agent (the visitor). The salt is created
 * at the first visit of each day of the site and only the current and the previous one are kept (48 hours at most):
 * it is random, never derived from the app key, so once deleted the hashes can no longer be linked to anything.
 *
 * A page view joins a visitor only if that visitor was first seen less than 24 hours ago (so nobody is recognized
 * after 24 hours, also across midnight thanks to the previous salt) and the same session if the previous page was
 * less than 30 minutes ago. After 24 hours the hashes are removed from the visits: what remains are random visitor
 * and session codes, which say nothing about the address.
 */
class Visitors
{
    /** How long a visitor is recognized */
    public const int WINDOW_HOURS = 24;

    /** Pause that closes a session */
    public const int SESSION_MINUTES = 30;

    /** Today's salt, once read (for this request) */
    private ?string $salt = null;

    public function __construct(private readonly Settings $settings) {}

    /**
     * Visitor and session of a page view, with the hashes to store.
     *
     * @return array{visitor_id: string, session_id: string, ip_hash: string, visitor_hash: string}
     */
    public function identify(string $ip, string $userAgent): array
    {
        $current = $this->currentSalt();
        $visitorHashes = $this->visitorHashes($ip, $userAgent);
        $now = now();

        $last = StatVisit::query()
            ->whereIn('visitor_hash', $visitorHashes)
            ->where('visited_at', '>=', $now->copy()->subHours(self::WINDOW_HOURS))
            ->whereNotNull('visitor_id')
            ->orderByDesc('visited_at')
            ->orderByDesc('id')
            ->first(['visitor_id', 'session_id', 'visited_at']);

        $visitor = $session = null;

        // the same visitor only within 24 hours of when it was first seen
        if ($last !== null && StatVisit::query()->where('visitor_id', $last->visitor_id)->min('visited_at') >= $now->copy()->subHours(self::WINDOW_HOURS)) {
            $visitor = (string) $last->visitor_id;

            if ($last->session_id !== null && $last->visited_at->gte($now->copy()->subMinutes(self::SESSION_MINUTES))) {
                $session = (string) $last->session_id;
            }
        }

        return [
            'visitor_id' => $visitor ?? self::code(),
            'session_id' => $session ?? self::code(),
            'ip_hash' => self::hash($current, $ip),
            'visitor_hash' => self::hash($current, $ip."\n".$userAgent),
        ];
    }

    /**
     * Hashes of an IP address with the current and the previous salt (to find its visits of the last 24 hours).
     *
     * @return list<string>
     */
    public function ipHashes(string $ip): array
    {
        return array_map(fn (string $salt): string => self::hash($salt, $ip), $this->existingSalts());
    }

    /**
     * Hashes of a visitor (IP address + browser) with the current and the previous salt.
     *
     * @return list<string>
     */
    public function visitorHashes(string $ip, string $userAgent): array
    {
        return array_map(fn (string $salt): string => self::hash($salt, $ip."\n".$userAgent), $this->existingSalts());
    }

    /**
     * Removes the hashes older than 24 hours and the salts no longer needed. From the tracker at most once an hour
     * (so the 24 hours hold even without a cron), from the cron every time.
     */
    public function prune(bool $force = false): int
    {
        if (! $force && ! Cache::add('resume.stats.visitors_prune', true, now()->addHour())) {
            return 0;
        }

        $cleared = StatVisit::query()
            ->where('visited_at', '<', now()->subHours(self::WINDOW_HOURS))
            ->where(fn ($query) => $query->whereNotNull('ip_hash')->orWhereNotNull('visitor_hash'))
            ->update(['ip_hash' => null, 'visitor_hash' => null]);

        DB::table('stat_salts')->where('day', '<', $this->today()->subDay()->toDateString())->delete();
        $this->salt = null;

        return $cleared;
    }

    /**
     * Salts that exist (without creating today's): used to look up visits, never to record them.
     *
     * @return list<string>
     */
    private function existingSalts(): array
    {
        return DB::table('stat_salts')
            ->where('day', '>=', $this->today()->subDay()->toDateString())
            ->orderByDesc('day')
            ->pluck('salt')
            ->map(fn (mixed $salt): string => (string) $salt)
            ->all();
    }

    /**
     * Today's salt, created at the first visit of the day.
     */
    private function currentSalt(): string
    {
        if ($this->salt !== null) {
            return $this->salt;
        }

        $today = $this->today()->toDateString();

        // two requests at the same moment: the unique day keeps only one salt
        DB::table('stat_salts')->insertOrIgnore(['day' => $today, 'salt' => bin2hex(random_bytes(32)), 'created_at' => now()]);

        return $this->salt = (string) DB::table('stat_salts')->where('day', $today)->value('salt');
    }

    private function today(): Carbon
    {
        return Carbon::today($this->settings->timezone());
    }

    private static function hash(string $salt, string $value): string
    {
        return substr(hash_hmac('sha256', $value, $salt), 0, 32);
    }

    /**
     * Random code of a visitor or a session (it does not come from the address).
     */
    private static function code(): string
    {
        return bin2hex(random_bytes(8));
    }
}
