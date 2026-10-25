<?php

declare(strict_types=1);

namespace App\Site\Security;

/**
 * Content Security Policy del sito pubblico: tutto dal nostro dominio, nessuno script in linea
 * tranne quello minimo che segnala "JavaScript attivo" (autorizzato con il suo hash).
 */
final class Csp
{
    /**
     * Script in linea del layout, prima del rendering: classe "js" su <html> e, se si arriva da un'altra
     * pagina del sito, anche "js-same-site" (i temi la usano per non ripetere le animazioni d'ingresso).
     */
    public const string JS_FLAG = "var h=document.documentElement;h.classList.add('js');"
        ."try{if(document.referrer&&new URL(document.referrer).origin===location.origin)h.classList.add('js-same-site')}catch(e){}";

    public static function header(bool $secure): string
    {
        $directives = [
            "default-src 'self'",
            "script-src 'self' ".self::hash(self::JS_FLAG),
            "style-src 'self'",
            "img-src 'self' data:",
            "font-src 'self'",
            "connect-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'none'",
            "frame-ancestors 'none'",
        ];

        if ($secure) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }

    public static function hash(string $script): string
    {
        return "'sha256-".base64_encode(hash('sha256', $script, true))."'";
    }
}
