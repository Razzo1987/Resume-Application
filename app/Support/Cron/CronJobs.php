<?php

declare(strict_types=1);

namespace App\Support\Cron;

use App\Models\StatVisit;
use App\Support\Settings\Settings;
use App\Support\Stats\Aggregator;
use App\Support\Stats\GeoIp;
use App\Support\Stats\GeoIpUpdater;
use App\Support\Update\AutoUpdate;
use App\Support\Update\UpdateStorage;
use Illuminate\Support\Facades\File;

/**
 * Elenco dei lavori programmati dell'app.
 */
final class CronJobs
{
    public static function register(Cron $cron): void
    {
        // Nuove versioni su GitHub (al massimo ogni 12 ore) e, col cron, download o installazione automatica
        $cron->register('updates', 30, light: true, run: fn (bool $fromCron): ?string => app(AutoUpdate::class)->run($fromCron));

        // Statistiche: totali giornalieri, cancellazione delle visite vecchie e degli IP admin scaduti
        $cron->register('stats', 60, light: true, run: fn (): string => app(Settings::class)->bool('stats.enabled') || StatVisit::query()->exists()
            ? app(Aggregator::class)->run()
            : 'off');

        // Database GeoLite: dal cron controllo e download; dall'admin (senza cron) solo il controllo, il download si fa
        // dalla pagina Statistiche
        $cron->register('geoip', 24 * 60, light: true, run: function (bool $fromCron): string {
            $updater = app(GeoIpUpdater::class);

            if (! app(Settings::class)->bool('stats.enabled') || app(GeoIp::class)->credentials() === null) {
                return 'off';
            }

            if ($fromCron) {
                return $updater->updateAll();
            }

            $updater->check();

            return $updater->needsUpdate() ? 'update available' : 'up to date';
        });

        // File temporanei dimenticati: caricamenti a blocchi interrotti, download parziali vecchi
        $cron->register('cleanup', 24 * 60, light: true, run: fn (): string => self::cleanup());
    }

    private static function cleanup(): string
    {
        $storage = app(UpdateStorage::class);
        $deleted = 0;
        $limit = now()->subDays(2)->getTimestamp();

        foreach ([$storage->uploadsDirectory().'/*', $storage->packagesDirectory().'/*.part'] as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file) && filemtime($file) < $limit) {
                    File::delete($file);
                    $deleted++;
                }
            }
        }

        return 'deleted '.$deleted;
    }
}
