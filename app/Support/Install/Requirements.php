<?php

declare(strict_types=1);

namespace App\Support\Install;

/**
 * Server requirements: PHP version, extensions and writable folders.
 */
class Requirements
{
    public const string MIN_PHP = '8.4.1';

    /** @var list<string> */
    public const array EXTENSIONS = ['ctype', 'dom', 'fileinfo', 'filter', 'gd', 'intl', 'mbstring', 'openssl', 'pdo_mysql', 'session', 'tokenizer', 'zip', 'zlib'];

    /**
     * @return list<array{label: string, ok: bool, detail: string}>
     */
    public function checks(): array
    {
        $checks = [[
            'label' => 'PHP '.self::MIN_PHP.'+',
            'ok' => version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'detail' => PHP_VERSION,
        ]];

        foreach (self::EXTENSIONS as $extension) {
            $checks[] = [
                'label' => 'ext-'.$extension,
                'ok' => extension_loaded($extension),
                'detail' => extension_loaded($extension) ? (string) (phpversion($extension) ?: 'ok') : '—',
            ];
        }

        foreach ($this->writablePaths() as $label => $path) {
            $checks[] = [
                'label' => $label,
                'ok' => is_dir($path) && is_writable($path),
                'detail' => $path,
            ];
        }

        return $checks;
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if (! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    public function writablePaths(): array
    {
        return [
            'core/' => base_path(),
            'core/storage' => storage_path(),
            'core/bootstrap/cache' => base_path('bootstrap/cache'),
            'webroot' => public_path(),
        ];
    }
}
