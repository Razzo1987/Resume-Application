<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Support\Update\AutoUpdate;
use App\Support\Update\ReleaseChecker;
use App\Support\Update\UpdateStorage;
use Illuminate\Support\Facades\Cache;

/**
 * Stato del sistema per l'admin: protezione di core/ (verificata al massimo una volta al giorno),
 * manutenzione, aggiornamento da completare, nuova versione disponibile.
 */
class SystemStatus
{
    public const string PROBE_CACHE_KEY = 'resume.core_probe';

    public function __construct(
        private readonly CoreProbe $probe,
        private readonly Maintenance $maintenance,
        private readonly Updater $updater,
        private readonly ReleaseChecker $releases,
        private readonly AutoUpdate $auto,
        private readonly UpdateStorage $storage,
    ) {}

    public function coreProtection(bool $refresh = false): string
    {
        if ($refresh) {
            Cache::forget(self::PROBE_CACHE_KEY);
        }

        return (string) Cache::remember(self::PROBE_CACHE_KEY, now()->addDay(), fn (): string => $this->probe->check());
    }

    /**
     * Avvisi da mostrare nella dashboard: chiave → livello (danger, warning).
     *
     * @return array<string, string>
     */
    public function alerts(): array
    {
        $alerts = [];

        if ($this->coreProtection() === CoreProbe::EXPOSED) {
            $alerts['core_exposed'] = 'danger';
        }

        if ($this->updater->isPending()) {
            $alerts['update_pending'] = 'warning';
        }

        if ($this->maintenance->isOn()) {
            $alerts['maintenance_on'] = 'warning';
        }

        $event = $this->auto->lastEvent();

        if ($event !== null && $event['type'] === 'failed' && $event['at']->gt(now()->subDays(7))) {
            $alerts['auto_update_failed'] = 'danger';
        }

        if ($event !== null && $event['type'] === 'installed' && $event['at']->gt(now()->subDays(3))) {
            $alerts['auto_update_installed'] = 'success';
        }

        $available = $this->updater->isPending() ? null : $this->releases->available();

        if ($available !== null) {
            $alerts[$this->storage->package($available->packageName()) !== null ? 'update_downloaded' : 'update_available'] = 'info';
        }

        return $alerts;
    }
}
