<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Cron\Cron;
use App\Support\Update\AutoUpdate;
use App\Support\Update\ReleaseChecker;
use App\Support\Update\UpdateRunner;
use Illuminate\Console\Command;
use Throwable;

/**
 * Command-line update.
 *
 *   php core/artisan resume:update --check     checks for new versions on GitHub
 *   php core/artisan resume:update --finish    completes an extracted update (migrations, cache, maintenance)
 *
 * Automatic download and installation are done by the cron, according to the System setting (resume:cron).
 */
class UpdateApp extends Command
{
    protected $signature = 'resume:update
        {--check : controlla le nuove versioni su GitHub}
        {--finish : completa un aggiornamento con i file già estratti}';

    protected $description = 'Controlla o completa un aggiornamento di Resume App';

    public function handle(ReleaseChecker $checker, UpdateRunner $runner, AutoUpdate $auto, Cron $cron): int
    {
        if ($this->option('finish')) {
            if (! $runner->isExtracted()) {
                $this->info('Nessun aggiornamento da completare.');

                return self::SUCCESS;
            }

            if (($runner->current()['mode'] ?? null) !== 'auto') {
                $this->line(implode("\n", $runner->finish()));

                return self::SUCCESS;
            }

            // Finishing an automatic update: the result is also recorded with the scheduled jobs
            try {
                $result = $auto->finish();
                $cron->recordResult('updates', true, $result);
                $this->line($result);

                return self::SUCCESS;
            } catch (Throwable $exception) {
                $cron->recordResult('updates', false, $exception->getMessage());
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $status = $checker->check();

        if ($status['error'] !== null) {
            $this->error($status['error']);

            return self::FAILURE;
        }

        $available = $checker->available();
        $this->line($available !== null ? 'Disponibile la versione '.$available->version : 'Nessuna versione più recente.');

        return self::SUCCESS;
    }
}
