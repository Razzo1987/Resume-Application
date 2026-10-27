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
 * Aggiornamento da riga di comando.
 *
 *   php core/artisan resume:update --check     controlla le nuove versioni su GitHub
 *   php core/artisan resume:update --finish    completa un aggiornamento estratto (migrazioni, cache, manutenzione)
 *
 * Il download e l'installazione automatici li fa il cron secondo l'impostazione di Sistema (resume:cron).
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

            // Completamento di un aggiornamento automatico: l'esito va anche tra i lavori programmati
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
