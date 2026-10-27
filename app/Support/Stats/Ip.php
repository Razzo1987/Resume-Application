<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * Truncated IP addresses: IPv4 without the last number (93.44.12.x), IPv6 to the first 48 bits (2a01:4f8:c0c::).
 * Enough for an approximate origin, not to identify a person.
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

    /**
     * A public internet address. Private and local ones (10.x, 192.168.x, 127.0.0.1, fd00::...) and the shared address
     * space of carrier and tunnel networks (100.64.0.0/10) are not: they are seen in local development, inside a
     * network, or behind a proxy that does not pass on the visitor's address (GitHub Codespaces port forwarding).
     */
    public static function isPublic(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            $long = (int) ip2long($ip);

            return ($long & 0xFFC00000) !== (ip2long('100.64.0.0') & 0xFFC00000);
        }

        return true;
    }
}
