<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Models\CvDocument;
use App\Site\Cache\PageCache;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use RuntimeException;
use ZipArchive;

/**
 * Esportazione dei contenuti in uno zip: tabelle in JSON, immagini caricate e PDF dei CV.
 * Non contiene il .env né gli utenti (password e segreti 2FA restano solo sul server).
 * Lo stesso zip si può reimportare (restore): serve anche a portare i contenuti da un'installazione all'altra.
 */
class Backup
{
    /** Tabelle tecniche escluse */
    public const array SKIP = ['users', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    public function create(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('Estensione PHP zip non disponibile.');
        }

        $directory = $this->directory();
        File::ensureDirectoryExists($directory);
        $name = 'resume-backup-'.now()->format('Ymd-His');
        $path = "{$directory}/{$name}.zip";

        // Due esportazioni nello stesso secondo (es. la copia di sicurezza prima di importare) non si sovrascrivono
        for ($copy = 2; file_exists($path); $copy++) {
            $path = "{$directory}/{$name}-{$copy}.zip";
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Impossibile creare {$path}");
        }

        $data = ['created_at' => now()->toAtomString(), 'version' => trim((string) @file_get_contents(base_path('VERSION'))), 'tables' => []];

        foreach ($this->tables() as $table) {
            $data['tables'][$table] = DB::table($table)->get()->map(fn (object $row): array => (array) $row)->all();
        }

        $zip->addFromString('database.json', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        foreach (Storage::disk('uploads')->allFiles() as $file) {
            $zip->addFile(Storage::disk('uploads')->path($file), 'uploads/'.$file);
        }

        foreach (Storage::disk(CvDocument::DISK)->allFiles(CvDocument::DIRECTORY) as $file) {
            $zip->addFile(Storage::disk(CvDocument::DISK)->path($file), $file);
        }

        $zip->close();

        return $path;
    }

    /**
     * Cartella delle esportazioni sul server (anche quelle copiate a mano con il File Manager).
     */
    public function directory(): string
    {
        return storage_path('app/private/backups');
    }

    /**
     * Esportazioni presenti sul server, dalla più recente: nome file => descrizione.
     *
     * @return array<string, string>
     */
    public function available(): array
    {
        $files = glob($this->directory().'/*.zip') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $list = [];

        foreach ($files as $file) {
            $list[basename($file)] = sprintf('%s — %s, %s', basename($file), date('d/m/Y H:i', (int) filemtime($file)), Number::fileSize((int) filesize($file), 1));
        }

        return $list;
    }

    /**
     * Percorso di un'esportazione presente sul server (solo il nome del file, mai percorsi).
     */
    public function find(string $name): ?string
    {
        $path = $this->directory().'/'.basename($name);

        return basename($name) === $name && str_ends_with($name, '.zip') && is_file($path) ? $path : null;
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        // Solo le tabelle del database dell'app (l'utente MySQL potrebbe vederne altri)
        $tables = array_map(
            fn (array $table): string => (string) $table['name'],
            Schema::getTables(DB::connection()->getDatabaseName()),
        );

        return array_values(array_diff($tables, self::SKIP));
    }

    /**
     * Sostituisce i contenuti con quelli di un'esportazione. Utenti, versione installata e .env non cambiano.
     * Prima di scrivere viene fatta un'esportazione dei contenuti attuali (percorso nel risultato).
     *
     * @return array{tables: int, rows: int, files: int, backup: string}
     */
    public function restore(string $zipPath): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException((string) __('admin.system.restore_errors.not_zip'));
        }

        try {
            $data = json_decode((string) $zip->getFromName('database.json'), true);

            if (! is_array($data) || ! is_array($data['tables'] ?? null)) {
                throw new RuntimeException((string) __('admin.system.restore_errors.not_backup'));
            }

            /** @var array<string, list<array<string, mixed>>> $tables */
            $tables = $data['tables'];
            $notExported = $this->checkSchema($tables['migrations'] ?? []);

            // Percorsi dei file: solo uploads/… e cv/…, senza risalite
            $files = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $name = (string) $zip->getNameIndex($index);

                if ($name === 'database.json' || str_ends_with($name, '/')) {
                    continue;
                }

                if (str_contains($name, '..') || str_contains($name, '\\') || ! preg_match('#^(uploads|'.CvDocument::DIRECTORY.')/[^/].*#', $name)) {
                    throw new RuntimeException((string) __('admin.system.restore_errors.unexpected_file', ['file' => $name]));
                }

                $files[$index] = $name;
            }

            $safety = $this->create();
            $installed = app(Settings::class)->get(Updater::VERSION_KEY);
            $current = $this->tables();
            $rows = 0;

            Schema::disableForeignKeyConstraints();

            try {
                DB::transaction(function () use ($tables, $current, $notExported, &$rows): void {
                    foreach ($tables as $table => $records) {
                        if ($table === 'migrations' || in_array($table, self::SKIP, true) || ! in_array($table, $current, true)) {
                            continue;
                        }

                        $columns = array_flip(Schema::getColumnListing($table));
                        DB::table($table)->delete();

                        foreach (array_chunk($records, 200) as $chunk) {
                            DB::table($table)->insert(array_map(
                                fn (array $record): array => array_intersect_key($record, $columns),
                                $chunk,
                            ));
                            $rows += count($chunk);
                        }
                    }

                    $this->replay($notExported);
                });
            } finally {
                Schema::enableForeignKeyConstraints();
            }

            // File: si sostituiscono caricamenti e CV
            $uploads = Storage::disk('uploads');
            $cvs = Storage::disk(CvDocument::DISK);

            foreach ($uploads->allFiles() as $file) {
                $uploads->delete($file);
            }

            $cvs->deleteDirectory(CvDocument::DIRECTORY);

            foreach ($files as $index => $name) {
                $content = (string) $zip->getFromIndex($index);

                str_starts_with($name, 'uploads/')
                    ? $uploads->put(substr($name, 8), $content)
                    : $cvs->put($name, $content);
            }
        } finally {
            $zip->close();
        }

        $settings = app(Settings::class);
        $settings->flush();

        if ($installed !== null) {
            $settings->set(Updater::VERSION_KEY, $installed);
        }

        app(PageCache::class)->flush();

        return ['tables' => count(array_diff(array_keys($tables), ['migrations'])), 'rows' => $rows, 'files' => count($files), 'backup' => basename($safety)];
    }

    /**
     * Un'esportazione fatta con una versione più recente (migrazioni sconosciute) non si importa.
     * Restituisce le migrazioni eseguite qui ma non ancora quando è stata fatta l'esportazione.
     *
     * @param  mixed  $migrations  righe della tabella migrations nell'esportazione
     * @return list<string>
     */
    private function checkSchema(mixed $migrations): array
    {
        $known = DB::table('migrations')->pluck('migration')->all();
        $incoming = array_column(is_array($migrations) ? $migrations : [], 'migration');

        if (array_diff($incoming, $known) !== []) {
            throw new RuntimeException((string) __('admin.system.restore_errors.newer'));
        }

        // Senza elenco delle migrazioni non si sa da che versione venga: non si ripete niente
        return $incoming === [] ? [] : array_values(array_diff($known, $incoming));
    }

    /**
     * Ripete sui contenuti importati le migrazioni di dati che l'esportazione non aveva ancora (es. la voce "Blog"
     * del menu nella 2.0.2): senza, importare un'esportazione più vecchia annullerebbe quelle modifiche.
     *
     * @param  list<string>  $migrations
     */
    private function replay(array $migrations): void
    {
        foreach ($migrations as $name) {
            $path = database_path("migrations/{$name}.php");

            if (! is_file($path)) {
                continue;
            }

            $migration = require $path;

            if ($migration instanceof ReplaysOnImport) {
                $migration->replay();
            }
        }
    }
}
