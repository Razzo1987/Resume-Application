<?php

declare(strict_types=1);

namespace App\Themes;

/**
 * Minimal check of theme version constraints ("requires": {"resume-app": "^2.0"}).
 * Allowed constraints: "*", "^2.0", "~2.1", ">=2.0", "2.1.*", "2.1.3"; several constraints separated by "||".
 * Development versions ("2.0.0-dev", "2.1.0-rc1") count as the final version.
 */
final class Compatibility
{
    public static function satisfies(string $version, string $constraint): bool
    {
        $version = self::normalize($version);

        if ($version === null) {
            return true; // development copy without VERSION: no check
        }

        foreach (explode('||', $constraint) as $alternative) {
            if (self::matches($version, trim($alternative))) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $version, string $constraint): bool
    {
        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        if (preg_match('/^\^(\d+)(?:\.(\d+))?(?:\.(\d+))?$/', $constraint, $m) === 1) {
            $min = sprintf('%d.%d.%d', $m[1], $m[2] ?? 0, $m[3] ?? 0);
            $max = (int) $m[1] === 0 ? sprintf('0.%d.0', (int) ($m[2] ?? 0) + 1) : sprintf('%d.0.0', (int) $m[1] + 1);

            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m) === 1) {
            $min = sprintf('%d.%d.%d', $m[1], $m[2], $m[3] ?? 0);
            $max = isset($m[3]) ? sprintf('%d.%d.0', $m[1], (int) $m[2] + 1) : sprintf('%d.0.0', (int) $m[1] + 1);

            return version_compare($version, $min, '>=') && version_compare($version, $max, '<');
        }

        if (preg_match('/^(>=|>|<=|<)\s*(\d+(?:\.\d+){0,2})$/', $constraint, $m) === 1) {
            return version_compare($version, self::normalize($m[2]) ?? '0.0.0', $m[1]);
        }

        if (preg_match('/^(\d+)\.(\d+)\.\*$/', $constraint, $m) === 1) {
            return str_starts_with($version, "{$m[1]}.{$m[2]}.");
        }

        $exact = self::normalize($constraint);

        return $exact !== null && version_compare($version, $exact, '==');
    }

    /**
     * "2.0.0-dev" → "2.0.0", "2.1" → "2.1.0"; null if it is not a version.
     */
    private static function normalize(string $version): ?string
    {
        if (preg_match('/^v?(\d+)(?:\.(\d+))?(?:\.(\d+))?/', trim($version), $m) !== 1) {
            return null;
        }

        return sprintf('%d.%d.%d', $m[1], $m[2] ?? 0, $m[3] ?? 0);
    }
}
