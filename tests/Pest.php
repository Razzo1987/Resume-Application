<?php

use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configurazione dei test
|--------------------------------------------------------------------------
| Feature: applicazione completa con database MySQL di test (ripulito a ogni test).
| Unit: classi isolate, senza applicazione.
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * CV dimostrativo bilingue (it-IT di default, en-GB) con i PDF su un disco finto.
 */
function seedDemo(): void
{
    Storage::fake('local');
    test()->seed(DemoSeeder::class);
}

/**
 * Host delle risorse caricate dalla pagina (script, stili, immagini, font), esclusi i normali link.
 *
 * @return list<string>
 */
function resourceHosts(string $html): array
{
    preg_match_all('#<(?:script|img|source|iframe|video|audio)\b[^>]*\ssrc="([^"]+)"#i', $html, $sources);
    preg_match_all('#<link\b[^>]*rel="(?:stylesheet|preload|modulepreload|icon|preconnect|dns-prefetch)"[^>]*href="([^"]+)"#i', $html, $links);
    preg_match_all('#<use\b[^>]*href="([^"]+)"#i', $html, $sprites);

    $hosts = [];

    foreach ([...$sources[1], ...$links[1], ...$sprites[1]] as $url) {
        $host = parse_url($url, PHP_URL_HOST);
        $hosts[] = is_string($host) ? $host : 'localhost';
    }

    return array_values(array_unique($hosts));
}

/**
 * Dati strutturati JSON-LD della pagina (tutti i nodi di tutti i blocchi).
 *
 * @return list<array<string, mixed>>
 */
function jsonLdNodes(string $html): array
{
    preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $blocks);

    $nodes = [];

    foreach ($blocks[1] as $json) {
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        $nodes = [...$nodes, ...($data['@graph'] ?? [$data])];
    }

    return $nodes;
}
