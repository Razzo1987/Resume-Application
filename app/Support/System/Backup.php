<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Models\CvDocument;
use App\Site\Cache\PageCache;
use App\Support\Settings\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Number;
use RuntimeException;
use ZipArchive;

/**
 * Content export to a zip: tables as JSON, uploaded images and CV PDFs.
 * It contains neither the .env nor the users (passwords and 2FA secrets stay on the server only).
 * The same zip can be imported again (restore): it is also used to move content from one installation to another.
 */
class Backup
{
    /** Excluded technical tables */
    public const array SKIP = ['users', 'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens', 'stat_visits', 'stat_daily', 'stat_admin_ips'];

    public function create(): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException((string) __('admin.messages.zip_missing'));
        }

        $directory = $this->directory();
        File::ensureDirectoryExists($directory);
        $name = 'resume-backup-'.now()->format('Ymd-His');
        $path = "{$directory}/{$name}.zip";

        // Two exports in the same second (e.g. the safety copy before an import) do not overwrite each other
        for ($copy = 2; file_exists($path); $copy++) {
            $path = "{$directory}/{$name}-{$copy}.zip";
        }

        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException((string) __('admin.messages.cannot_create', ['path' => $path]));
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
     * Folder of the exports on the server (including those copied by hand with the File Manager).
     */
    public function directory(): string
    {
        return storage_path('app/private/backups');
    }

    /**
     * Date of the most recent content export (made by hand or before an update or an import).
     */
    public function lastCreatedAt(): ?Carbon
    {
        $times = array_map(fn (string $file): int => (int) filemtime($file), glob($this->directory().'/*.zip') ?: []);

        return $times !== [] ? Carbon::createFromTimestamp(max($times)) : null;
    }

    /**
     * Exports present on the server, most recent first: file name => description.
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
     * Path of an export present on the server (the file name only, never paths).
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
        // Only the tables of the app's database (the MySQL user might see others)
        $tables = array_map(
            fn (array $table): string => (string) $table['name'],
            Schema::getTables(DB::connection()->getDatabaseName()),
        );

        return array_values(array_diff($tables, self::SKIP));
    }

    /**
     * Replaces the content with that of an export. Users, installed version and .env do not change.
     * Before writing, an export of the current content is made (path in the result).
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

            // File paths: only uploads/… and cv/…, no parent traversal
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

            // Files: uploads and CVs are replaced
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
     * An export made with a newer version (unknown migrations) is not imported.
     * Returns the migrations run here but not yet run when the export was made.
     *
     * @param  mixed  $migrations  rows of the migrations table in the export
     * @return list<string>
     */
    private function checkSchema(mixed $migrations): array
    {
        $known = DB::table('migrations')->pluck('migration')->all();
        $incoming = array_column(is_array($migrations) ? $migrations : [], 'migration');

        if (array_diff($incoming, $known) !== []) {
            throw new RuntimeException((string) __('admin.system.restore_errors.newer'));
        }

        // Without the list of migrations there is no way to know which version it comes from: nothing is replayed
        return $incoming === [] ? [] : array_values(array_diff($known, $incoming));
    }

    /**
     * Replays on the imported content the data migrations that the export did not have yet (e.g. the "Blog" menu
     * item in 2.0.2): without this, importing an older export would undo those changes.
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
