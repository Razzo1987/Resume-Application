<?php

declare(strict_types=1);

namespace App\Site\Contact;

/**
 * Email e telefono non compaiono in chiaro nell'HTML: il valore è codificato in un attributo data-*
 * e lo script del tema lo ricompone. Senza JavaScript si vede un invito ad attivarlo.
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
     * Numero per il link tel: (solo cifre e "+").
     */
    public function telephone(string $phone): string
    {
        return (string) preg_replace('/[^\d+]/', '', $phone);
    }
}
