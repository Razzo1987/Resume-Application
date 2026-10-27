<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Models\StatAdminIp;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * IP degli amministratori: chi usa l'admin da un indirizzo è l'amministratore, quindi le visite al sito da lì
 * non si contano, per stats.admin_ip_days giorni dall'ultimo accesso all'admin (default 14). Con un IP condiviso
 * (ufficio, rete mobile) l'esclusione toccherebbe anche altre persone: per questo scade da sola.
 */
class AdminIps
{
    public function __construct(private readonly Settings $settings) {}

    /**
     * Annota l'IP di una pagina dell'admin (al massimo una scrittura ogni 10 minuti per indirizzo).
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
     * Toglie gli indirizzi scaduti.
     */
    public function prune(): int
    {
        return StatAdminIp::query()->where('last_seen_at', '<', now()->subDays($this->days()))->delete();
    }
}
