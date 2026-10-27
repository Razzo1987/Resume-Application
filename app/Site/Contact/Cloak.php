<?php

declare(strict_types=1);

namespace App\Site\Contact;

/**
 * Email and phone never appear in plain text in the HTML: the value is encoded in a data-* attribute
 * and the theme script puts it back together. Without JavaScript a prompt to enable it is shown.
 */
final class Cloak
{
    public function encode(string $value): string
    {
        return base64_encode(strrev($value));
    }

    public function decode(string $encoded): string
    {
        return strrev((string) base64_decode($encoded, true));
    }

    /**
     * Number for the tel: link (digits and "+" only).
     */
    public function telephone(string $phone): string
    {
        return (string) preg_replace('/[^\d+]/', '', $phone);
    }
}
