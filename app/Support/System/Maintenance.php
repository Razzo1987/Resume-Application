<?php

declare(strict_types=1);

namespace App\Support\System;

use Illuminate\Support\Facades\File;

/**
 * Manutenzione del sito pubblico (durante gli aggiornamenti): i visitatori vedono una pagina "torniamo presto",
 * l'area admin continua a funzionare. Diversa da "php artisan down", che blocca anche l'admin.
 */
class Maintenance
{
    public function path(): string
    {
        return storage_path('framework/resume-maintenance');
    }

    public function isOn(): bool
    {
        return is_file($this->path());
    }

    public function on(): void
    {
        File::ensureDirectoryExists(dirname($this->path()));
        file_put_contents($this->path(), (string) now()->toAtomString());
    }

    public function off(): void
    {
        File::delete($this->path());
    }
}
