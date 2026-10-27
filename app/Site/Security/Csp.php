<?php

declare(strict_types=1);

namespace App\Site\Security;

/**
 * Content Security Policy of the public site: everything from our domain, no inline scripts
 * except the minimal one that signals "JavaScript enabled" (allowed through its hash).
 */
final class Csp
{
    /**
     * Inline script of the layout, before rendering: "js" class on <html> and, when coming from another
     * page of the site, also "js-same-site" (themes use it to avoid repeating the entrance animations).
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
