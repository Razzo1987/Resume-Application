<?php

declare(strict_types=1);

namespace App\Support\Cron;

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
