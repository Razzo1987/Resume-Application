<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * Indirizzi IP accorciati: IPv4 senza l'ultimo numero (93.44.12.x), IPv6 ai primi 48 bit (2a01:4f8:c0c::).
 * Bastano per la provenienza approssimativa, non per riconoscere una persona.
 */
final class Ip
{
    public static function truncate(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $parts = explode('.', $ip);

            return $parts[0].'.'.$parts[1].'.'.$parts[2].'.x';
        }

        $binary = @inet_pton($ip);

        if ($binary === false || strlen($binary) !== 16) {
            return 'x';
        }

        return (string) inet_ntop(substr($binary, 0, 6).str_repeat("\0", 10));
    }
}
