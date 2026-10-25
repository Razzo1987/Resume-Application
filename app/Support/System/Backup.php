<?php

declare(strict_types=1);

namespace App\Support\System;

use App\Models\CvDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Esportazione dei contenuti in uno zip: tabelle in JSON, immagini caricate e PDF dei CV.
 * Non contiene il .env né gli utenti (password e segreti 2FA restano solo sul server).
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

        $directory = storage_path('app/private/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/resume-backup-'.now()->format('Ymd-His').'.zip';

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
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = array_map(
            fn (array $table): string => (string) $table['name'],
            Schema::getTables(),
        );

        return array_values(array_diff($tables, self::SKIP));
    }
}
