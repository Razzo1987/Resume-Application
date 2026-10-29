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
 * List of the app's scheduled jobs.
 */
final class CronJobs
{
    public static function register(Cron $cron): void
    {
        // New versions on GitHub (at most every 12 hours) and, from the cron, automatic download or installation
        $cron->register('updates', 30, light: true, run: fn (bool $fromCron): ?string => app(AutoUpdate::class)->run($fromCron));

        // Statistics: daily totals, deletion of old visits and of expired admin IPs
        $cron->register('stats', 60, light: true, run: fn (): string => app(Settings::class)->bool('stats.enabled') || StatVisit::query()->exists()
            ? app(Aggregator::class)->run()
            : 'off');

        // GeoLite database: from the cron, check and download; from the admin (without cron) only the check, the
        // download is done from the Statistics page
        $cron->register('geoip', 24 * 60, light: true, run: function (bool $fromCron): string {
            $updater = app(GeoIpUpdater::class);

            if (! app(Settings::class)->bool('stats.enabled')) {
                return 'off';
            }

            // Statistics on but no account: say what is missing instead of "off"
            if (app(GeoIp::class)->credentials() === null) {
                return 'no MaxMind account';
            }

            if ($fromCron) {
                return $updater->updateAll();
            }

            $updater->check();

            return $updater->needsUpdate() ? 'update available' : 'up to date';
        });

        // Leftover temporary files: interrupted chunked uploads, old partial downloads
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
