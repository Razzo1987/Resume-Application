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
        {--files= : public_html folder of v1 (img/, attachment/, resume/)}
        {--sql= : v1 .sql dump to load first into the "legacy" database}
        {--volunteering= : v1 ids of the volunteering experiences, comma separated}
        {--cv=* : current CV per language, e.g. --cv=it-IT=/path/cv.pdf}
        {--fresh : first delete experience, projects, education, certifications, skills, organizations and social links}
        {--force : do not ask for confirmation}';

    protected $description = 'Imports the content of version 1 (one row per language) into version 2';

    public function handle(V1Importer $importer): int
    {
        $files = (string) $this->option('files');

        if ($files === '' || ! is_dir($files)) {
            $this->error('Give the public_html folder of version 1 with --files.');

            return self::FAILURE;
        }

        $legacy = DB::connection('legacy');

        try {
            if ($sql = $this->option('sql')) {
                $legacy->unprepared((string) file_get_contents((string) $sql));
                $this->info("Dump loaded into the \"{$legacy->getDatabaseName()}\" database.");
            }

            $legacy->table('profile')->count();
        } catch (Throwable $exception) {
            $this->error('The v1 database cannot be reached: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('fresh') && ! $this->option('force') && ! $this->confirm('Delete the existing content before importing?')) {
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
