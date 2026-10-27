<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Cron\Cron;
use Illuminate\Console\Command;

/**
 * Resume App scheduled jobs. Usually started by "schedule:run" (the hosting cron job); it can also be run by hand.
 *
 *   php core/artisan resume:cron              jobs that are due now
 *   php core/artisan resume:cron --job=geoip  a single job, even if it is not due yet
 */
class RunCron extends Command
{
    protected $signature = 'resume:cron {--job= : runs only this job}';

    protected $description = 'Runs the scheduled jobs (updates, GeoLite, statistics, clean-up)';

    public function handle(Cron $cron): int
    {
        $job = $this->option('job');

        if (is_string($job) && $job !== '' && ! in_array($job, $cron->names(), true)) {
            $this->error('Unknown job. Available: '.implode(', ', $cron->names()));

            return self::FAILURE;
        }

        $results = $cron->run(fromCron: true, only: is_string($job) && $job !== '' ? $job : null);

        foreach ($results as $name => $result) {
            $this->line(str_pad($name, 10).' '.$result);
        }

        return collect($results)->contains(fn (string $result): bool => str_starts_with($result, 'error:')) ? self::FAILURE : self::SUCCESS;
    }
}
