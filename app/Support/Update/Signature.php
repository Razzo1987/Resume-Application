<?php

declare(strict_types=1);

namespace App\Support\Update;

use Throwable;

/**
 * Ed25519 signatures of the release packages (file "{package}.zip.sig": the signature in base64).
 *
 * The release workflow signs every package with the maintainer's key (a secret of the "release" environment, used only
 * for tags signed with the maintainer's GPG key); the app checks the signature with the public keys below before
 * installing a downloaded update. A theme declares its own keys in theme.json ("signing_keys"), and its updates are
 * checked with the keys of the installed version. A fork of the app replaces KEYS with its own.
 */
final class Signature
{
    /** Public keys of the maintainer (Ed25519, base64): the one in use, then the reserve one kept offline */
    public const array KEYS = [
        'NUqguz1xq9Hpg1iVRxFVOH9aMqD1eqIxdBAfHz3qmF0=',
        'Kyxl+jOeNEB7RwvqICz3mBXHr/nNifbz1SeEld3ecXQ=',
    ];

    /**
     * Keys of the app's packages (config resume.updates.signing_keys only in the tests).
     *
     * @return list<string>
     */
    public static function appKeys(): array
    {
        $keys = self::keys(config('resume.updates.signing_keys'));

        return $keys !== [] ? $keys : self::KEYS;
    }

    /**
     * Valid public keys among the given values (a key or a list of keys, base64 of 32 bytes).
     *
     * @return list<string>
     */
    public static function keys(mixed $values): array
    {
        $keys = [];

        foreach (is_array($values) ? $values : [$values] as $value) {
            if (is_string($value) && preg_match('#^[A-Za-z0-9+/]{43}=$#', $value) === 1 && strlen((string) base64_decode($value, true)) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
                $keys[] = $value;
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * True if the signature (content of the .sig file) of the file is valid for one of the keys.
     *
     * @param  list<string>  $keys
     */
    public static function verify(string $file, string $signature, array $keys): bool
    {
        $raw = base64_decode(trim($signature), true);

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_SIGN_BYTES || ! is_file($file)) {
            return false;
        }

        $content = (string) file_get_contents($file);

        foreach ($keys as $key) {
            try {
                if (sodium_crypto_sign_verify_detached($raw, $content, (string) base64_decode($key, true))) {
                    return true;
                }
            } catch (Throwable) {
                // invalid key: try the next one
            }
        }

        return false;
    }
}
