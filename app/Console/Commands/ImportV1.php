<?php

namespace App\Console\Commands;

use App\Support\Import\V1Importer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Imports the content of Resume Application 1.x.
 *
 *   php artisan resume:import-v1 --files=/path/public_html_v1 --volunteering=9,13 --cv=it-IT="/…/CV.pdf"
 *
 * The v1 database is read through the "legacy" connection (LEGACY_DB_* in .env, by default the resume_v1 database
 * on the same server); with --sql a dump is first loaded into that database.
 */
class ImportV1 extends Command
{
    protected $signature = 'resume:import-v1
        {--files= : cartella public_html della v1 (img/, attachment/, resume/)}
        {--sql= : dump .sql della v1 da caricare prima nel database "legacy"}
        {--volunteering= : id v1 delle esperienze di volontariato, separati da virgola}
        {--cv=* : CV corrente per lingua, es. --cv=it-IT=/percorso/cv.pdf}
        {--fresh : elimina prima esperienze, progetti, formazione, certificazioni, competenze, organizzazioni e social}
        {--force : non chiedere conferma}';

    protected $description = 'Importa i contenuti della versione 1 (una riga per lingua) nella versione 2';

    public function handle(V1Importer $importer): int
    {
        $files = (string) $this->option('files');

        if ($files === '' || ! is_dir($files)) {
            $this->error('Indica con --files la cartella public_html della versione 1.');

            return self::FAILURE;
        }

        $legacy = DB::connection('legacy');

        try {
            if ($sql = $this->option('sql')) {
                $legacy->unprepared((string) file_get_contents((string) $sql));
                $this->info("Dump caricato nel database \"{$legacy->getDatabaseName()}\".");
            }

            $legacy->table('profile')->count();
        } catch (Throwable $exception) {
            $this->error('Database della v1 non raggiungibile: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->option('force') && ! $this->confirm('Eliminare i contenuti già presenti prima di importare?')) {
            return self::FAILURE;
        }

        $cvs = [];

        foreach ((array) $this->option('cv') as $item) {
            [$code, $path] = array_pad(explode('=', (string) $item, 2), 2, '');
            $cvs[trim($code)] = trim($path);
        }

        $volunteering = array_values(array_filter(array_map('intval', explode(',', (string) $this->option('volunteering')))));

        foreach ($importer->import($legacy, $files, $volunteering, $cvs, (bool) $this->option('fresh')) as $line) {
            str_starts_with($line, '⚠') ? $this->warn($line) : $this->line("✓ {$line}");
        }

        return self::SUCCESS;
    }
}
