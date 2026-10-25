<?php

declare(strict_types=1);

namespace App\Support\Update;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Number;
use Throwable;

/**
 * File degli aggiornamenti in core/storage (mai nella webroot, mai nelle esportazioni dei contenuti):
 * pacchetti scaricati o caricati, caricamenti a blocchi in corso, esito dell'ultimo controllo su GitHub,
 * token di GitHub (cifrato con la APP_KEY), aggiornamento in corso.
 */
class UpdateStorage
{
    /**
     * @param  string|null  $storage  cartella storage dell'app (di default quella di Laravel; serve ai test)
     */
    public function __construct(private ?string $storage = null) {}

    public function directory(): string
    {
        return $this->storage().'/app/private/updates';
    }

    public function packagesDirectory(): string
    {
        return $this->directory().'/packages';
    }

    public function uploadsDirectory(): string
    {
        return $this->directory().'/uploads';
    }

    /**
     * Stato dell'estrazione: finché esiste, index.php non avvia l'app.
     */
    public function stateFile(): string
    {
        return $this->storage().'/framework/resume-update.json';
    }

    /**
     * Copia dello script di estrazione della versione che avvia l'aggiornamento.
     */
    public function updaterFile(): string
    {
        return $this->storage().'/framework/resume-updater.php';
    }

    /**
     * Dati dell'aggiornamento avviato dall'admin, per completarlo dopo l'estrazione.
     */
    public function runFile(): string
    {
        return $this->directory().'/run.json';
    }

    public function checkFile(): string
    {
        return $this->directory().'/check.json';
    }

    public function tokenFile(): string
    {
        return $this->directory().'/github-token';
    }

    public function isExtracting(): bool
    {
        return is_file($this->stateFile());
    }

    /**
     * Pacchetti sul server, dal più recente: nome file => descrizione.
     *
     * @return array<string, string>
     */
    public function packages(): array
    {
        $files = glob($this->packagesDirectory().'/*.zip') ?: [];
        usort($files, fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));

        $list = [];

        foreach ($files as $file) {
            $list[basename($file)] = sprintf('%s (%s, %s)', basename($file), date('d/m/Y H:i', (int) filemtime($file)), Number::fileSize((int) filesize($file), 1));
        }

        return $list;
    }

    /**
     * Percorso di un pacchetto sul server, solo per nomi semplici (niente cartelle).
     */
    public function package(string $name): ?string
    {
        $path = $this->packagesDirectory().'/'.basename($name);

        return basename($name) === $name && preg_match('/^[A-Za-z0-9._-]+\.zip$/', $name) === 1 && is_file($path) ? $path : null;
    }

    public function deletePackage(string $name): void
    {
        if (($path = $this->package($name)) !== null) {
            File::delete($path);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readJson(string $file): ?array
    {
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        return is_array($data) ? $data : null;
    }

    /**
     * Scrittura atomica (file temporaneo e rinomina).
     *
     * @param  array<string, mixed>  $data
     */
    public function writeJson(string $file, array $data): void
    {
        File::ensureDirectoryExists(dirname($file));
        file_put_contents($file.'.tmp', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        rename($file.'.tmp', $file);
    }

    /**
     * Token di GitHub: quello del .env (RESUME_UPDATE_TOKEN) ha la precedenza su quello inserito dall'admin.
     */
    public function token(): ?string
    {
        $env = config('resume.updates.token');

        if (is_string($env) && $env !== '') {
            return $env;
        }

        try {
            $token = is_file($this->tokenFile()) ? Crypt::decryptString((string) file_get_contents($this->tokenFile())) : null;
        } catch (Throwable) {
            $token = null; // APP_KEY cambiata: il token va reinserito
        }

        return $token !== null && $token !== '' ? $token : null;
    }

    /**
     * Da dove viene il token: "env", "admin" o null.
     */
    public function tokenSource(): ?string
    {
        $env = config('resume.updates.token');

        return match (true) {
            is_string($env) && $env !== '' => 'env',
            $this->token() !== null => 'admin',
            default => null,
        };
    }

    public function setToken(?string $token): void
    {
        if ($token === null || trim($token) === '') {
            File::delete($this->tokenFile());

            return;
        }

        File::ensureDirectoryExists($this->directory());
        file_put_contents($this->tokenFile(), Crypt::encryptString(trim($token)));
        @chmod($this->tokenFile(), 0600);
    }

    private function storage(): string
    {
        return rtrim($this->storage ?? storage_path(), '/');
    }
}
