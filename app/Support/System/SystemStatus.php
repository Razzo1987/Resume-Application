<?php

declare(strict_types=1);

namespace App\Support\System;

use Illuminate\Support\Facades\Cache;

/**
 * Stato del sistema per l'admin: protezione di core/ (verificata al massimo una volta al giorno),
 * manutenzione, aggiornamento da completare.
 */
class SystemStatus
{
    public const string PROBE_CACHE_KEY = 'resume.core_probe';

    public function __construct(
        private readonly CoreProbe $probe,
        private readonly Maintenance $maintenance,
        private readonly Updater $updater,
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

        return $alerts;
    }
}
