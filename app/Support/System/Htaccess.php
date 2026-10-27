<?php

declare(strict_types=1);

namespace App\Support\System;

use RuntimeException;

/**
 * Blocco "# BEGIN resume-app … # END resume-app" nel .htaccess della webroot.
 * The rest of the file (e.g. the PHP version handler added by the hosting panel) is never touched.
 */
class Htaccess
{
    public const string BEGIN = '# BEGIN resume-app';

    public const string END = '# END resume-app';

    private ?string $path = null;

    /**
     * Usa un altro file (test, installazioni particolari).
     */
    public function usePath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function path(): string
    {
        return $this->path ?? public_path('.htaccess');
    }

    public function template(): string
    {
        return base_path('stubs/htaccess.dist');
    }

    /**
     * Blocco con le opzioni scelte (HTTPS obbligatorio, dominio senza www).
     */
    public function block(bool $forceHttps, bool $stripWww): string
    {
        $template = (string) file_get_contents($this->template());
        $start = strpos($template, self::BEGIN);
        $end = strpos($template, self::END);

        if ($start === false || $end === false) {
            throw new RuntimeException('Modello htaccess.dist senza marcatori resume-app');
        }

        $block = substr($template, $start, $end - $start + strlen(self::END));

        $block = str_replace('#{{FORCE_HTTPS}}', $forceHttps ? '' : '#', $block);

        return str_replace('#{{STRIP_WWW}}', $stripWww ? '' : '#', $block);
    }

    /**
     * Scrive (o sostituisce) il blocco, conservando il resto del file.
     */
    public function write(bool $forceHttps, bool $stripWww): void
    {
        $path = $this->path();
        $current = is_file($path) ? (string) file_get_contents($path) : '';
        $block = $this->block($forceHttps, $stripWww);

        $pattern = '/'.preg_quote(self::BEGIN, '/').'.*?'.preg_quote(self::END, '/').'/s';

        $content = preg_match($pattern, $current) === 1
            ? (string) preg_replace_callback($pattern, fn (): string => $block, $current, 1)
            : rtrim($block)."\n".($current !== '' ? "\n".ltrim($current) : '');

        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException("Impossibile scrivere {$path}");
        }
    }

    /**
     * Opzioni attualmente attive nel blocco.
     *
     * @return array{installed: bool, force_https: bool, strip_www: bool}
     */
    public function options(): array
    {
        $content = is_file($this->path()) ? (string) file_get_contents($this->path()) : '';

        return [
            'installed' => str_contains($content, self::BEGIN),
            'force_https' => preg_match('/^\s*RewriteCond %\{HTTPS\} !=on/m', $content) === 1,
            'strip_www' => preg_match('/^\s*RewriteCond %\{HTTP_HOST\} \^www/m', $content) === 1,
        ];
    }
}
