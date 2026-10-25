<?php

declare(strict_types=1);

namespace App\Support\Install;

/**
 * L'applicazione è installata quando esiste il file .env (il pacchetto di rilascio non lo contiene).
 * Finché manca, ogni richiesta porta all'installer web.
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
