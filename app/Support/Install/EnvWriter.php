<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/**
 * Scrive il file .env dell'installazione (permessi 0600 dove il sistema lo consente).
 */
class EnvWriter
{
    /**
     * @param  array<string, string|int|bool|null>  $values
     */
    public function write(string $path, array $values): void
    {
        $lines = ['# Resume App — configurazione generata dall\'installer il '.date('Y-m-d H:i')];

        foreach ($values as $key => $value) {
            // Chiavi fatte di soli spazi: righe vuote per separare i gruppi
            $lines[] = trim((string) $key) === '' ? '' : $key.'='.self::format($value);
        }

        $content = implode("\n", $lines)."\n";

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("Impossibile scrivere {$path}");
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

        // Valori semplici senza virgolette; il resto tra virgolette con \ e " protetti (anche $ per l'interpolazione)
        if ($value !== '' && preg_match('/^[A-Za-z0-9_.:\/@+=-]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value).'"';
    }
}
