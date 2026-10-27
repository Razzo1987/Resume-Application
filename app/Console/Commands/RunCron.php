<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Cron\Cron;
use Illuminate\Console\Command;

/**
 * Lavori programmati di Resume App. Di solito parte da "schedule:run" (il cron di cPanel); si può lanciare anche a mano.
 *
 *   php core/artisan resume:cron              lavori da fare adesso
 *   php core/artisan resume:cron --job=geoip  un solo lavoro, anche se non è ancora il momento
 */
class RunCron extends Command
{
    protected $signature = 'resume:cron {--job= : esegue solo questo lavoro}';

    protected $description = 'Esegue i lavori programmati (aggiornamenti, GeoLite, statistiche, pulizia)';

    public function handle(Cron $cron): int
    {
        $job = $this->option('job');

        if (is_string($job) && $job !== '' && ! in_array($job, $cron->names(), true)) {
            $this->error('Lavoro sconosciuto. Disponibili: '.implode(', ', $cron->names()));

            return self::FAILURE;
        }

        $results = $cron->run(fromCron: true, only: is_string($job) && $job !== '' ? $job : null);

        foreach ($results as $name => $result) {
            $this->line(str_pad($name, 10).' '.$result);
        }

        return collect($results)->contains(fn (string $result): bool => str_starts_with($result, 'error:')) ? self::FAILURE : self::SUCCESS;
    }
}
