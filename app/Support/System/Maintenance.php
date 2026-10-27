<?php

declare(strict_types=1);

namespace App\Support\System;

use Illuminate\Support\Facades\File;

/**
 * Maintenance mode for the public site (during updates): visitors see a "we'll be back soon" page, the admin area
 * keeps working. Different from "php artisan down", which also blocks the admin.
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
