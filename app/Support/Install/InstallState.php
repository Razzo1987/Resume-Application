<?php

declare(strict_types=1);

namespace App\Support\Install;

/**
 * The application is installed when the .env file exists (the release package does not contain it).
 * As long as it is missing, every request leads to the web installer.
 */
class InstallState
{
    public function envPath(): string
    {
        return (string) (config('resume.installer.env_path') ?: app()->environmentFilePath());
    }

    public function isInstalled(): bool
    {
        return is_file($this->envPath());
    }
}
