<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/**
 * Writes the installation's .env file (0600 permissions where the system allows it).
 */
class EnvWriter
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function write(string $path, array $values): void
    {
        $lines = ['# Resume App - configuration written by the installer on '.date('Y-m-d H:i')];

        foreach ($values as $key => $value) {
            // Keys made only of spaces: blank lines that separate the groups
            $lines[] = trim((string) $key) === '' ? '' : $key.'='.self::format($value);
        }

        $content = implode("\n", $lines)."\n";

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException((string) __('admin.messages.cannot_write', ['path' => $path]));
        }

        @chmod($path, 0600);
    }

    public static function format(string|int|bool|null $value): string
    {
        if ($value === null) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        $value = (string) $value;

        // Simple values without quotes; everything else in quotes with \ and " escaped (also $, for interpolation)
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
