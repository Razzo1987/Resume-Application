<?php

/*
 * Proxy fidati (letti dal middleware TrustProxies di Laravel).
 *
 * Servono solo se il sito sta dietro un proxy che cambia l'indirizzo della richiesta: CDN o tunnel (Cloudflare),
 * inoltro delle porte di GitHub Codespaces, alcuni hosting. Allora indirizzo, protocollo e IP del visitatore
 * vanno presi dagli header X-Forwarded-*, altrimenti per esempio dopo il login si torna a "localhost".
 *
 * RESUME_TRUSTED_PROXIES: vuoto (default, nessun proxy), "*" (qualsiasi proxy) oppure un elenco di IP o reti
 * separati da virgole (es. "10.0.0.1,192.168.0.0/16"). Con "*" e nessun proxy davanti al sito chiunque potrebbe
 * inventarsi IP e indirizzo con gli header: usarlo solo se il server è raggiungibile soltanto attraverso il proxy.
 */
return [
    'proxies' => env('RESUME_TRUSTED_PROXIES') ?: null,
];
