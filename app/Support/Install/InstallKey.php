<?php

declare(strict_types=1);

namespace App\Support\Install;

use App\Support\System\CoreProbe;
use Illuminate\Support\Str;

/**
 * Installation key: until the .env exists anybody can open the installer. With the database on the same server
 * ("localhost") only who knows its credentials can complete it; with a database elsewhere somebody could use one of
 * their own and become the administrator, so the installer also asks for this key. It is written in a file of the
 * application folder, which only the owner of the hosting account can open, and deleted at the end of the
 * installation. If that folder could be reached from the browser the key would not help, but then the installation
 * stops anyway (CoreProbe).
 */
class InstallKey
{
    /** Database servers on this same machine: no key needed */
    public const array LOCAL_HOSTS = ['localhost', '127.0.0.1', '::1', '[::1]'];

    public function __construct(private readonly CoreProbe $probe) {}

    public static function isLocal(string $host): bool
    {
        return in_array(strtolower(trim($host)), self::LOCAL_HOSTS, true);
    }

    public function path(): string
    {
        return storage_path('install-key.txt');
    }

    /**
     * Where the owner finds the file, from the web root (the folder seen in the file manager of the hosting).
     */
    public function displayPath(): string
    {
        return ($this->probe->isReleaseLayout() ? 'core/' : '').'storage/install-key.txt';
    }

    /**
     * The key, created on first use.
     */
    public function value(): string
    {
        $current = is_file($this->path()) ? trim((string) file_get_contents($this->path())) : '';

        if ($current !== '') {
            return $current;
        }

        $key = implode('-', str_split(Str::upper(Str::random(16)), 4));
        @file_put_contents($this->path(), $key."\n", LOCK_EX);
        @chmod($this->path(), 0600);

        return $key;
    }

    public function matches(?string $given): bool
    {
        $given = strtoupper(trim((string) $given));

        return $given !== '' && hash_equals($this->value(), $given);
    }

    public function delete(): void
    {
        if (is_file($this->path())) {
            @unlink($this->path());
        }
    }
}
