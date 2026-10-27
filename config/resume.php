<?php

/*
 * Resume App — configurazione di base.
 *
 * Questi valori sono i default di installazione. Dalla fase M1 il tema attivo,
 * le lingue e le altre preferenze si gestiscono dal pannello admin (tabella settings).
 */

return [

    // Percorso del pannello di amministrazione (es. "admin" → https://sito/admin).
    'admin_path' => env('RESUME_ADMIN_PATH', 'admin'),

    // Lingua dell'interfaccia admin (traduzioni Filament: "it", "en", ...).
    'admin_locale' => env('RESUME_ADMIN_LOCALE', 'it'),

    // Pagina del progetto open source (link "Realizzato con Resume App" nel footer).
    'project_url' => 'https://github.com/Razzo1987/Resume-Application',

    // Cache HTML delle pagine pubbliche (disattivabile in sviluppo con RESUME_PAGE_CACHE=false).
    'page_cache' => (bool) env('RESUME_PAGE_CACHE', true),

    // Installer: percorso del .env che segna l'installazione (vuoto = quello di Laravel). Serve solo ai test.
    'installer' => [
        'env_path' => null,
    ],

    // Aggiornamenti: nuove versioni dalle release del repository GitHub (tag vX.Y.Z con lo zip del pacchetto).
    // Il token serve solo se il repository è privato: di solito si inserisce dall'admin (Sistema), qui ha la precedenza.
    'updates' => [
        'repository' => env('RESUME_UPDATE_REPOSITORY', 'Razzo1987/Resume-Application'),
        'api' => env('RESUME_UPDATE_API', 'https://api.github.com'),
        'token' => env('RESUME_UPDATE_TOKEN'),
        'check_every_hours' => 12,
    ],

    // Provenienza delle visite (statistiche): database GeoLite2 di MaxMind scaricati con un account gratuito.
    // Account ID e License Key di solito si inseriscono dall'admin (Statistiche); qui hanno la precedenza.
    'geoip' => [
        'account_id' => env('RESUME_GEOIP_ACCOUNT_ID'),
        'license_key' => env('RESUME_GEOIP_LICENSE_KEY'),
        'download_url' => env('RESUME_GEOIP_DOWNLOAD_URL', 'https://download.maxmind.com/geoip/databases/{edition}/download?suffix=tar.gz'),
    ],

    'themes' => [
        // Tema attivo. Ogni tema eredita dal tema "base" le view e le stringhe che non ridefinisce.
        'active' => env('RESUME_THEME', 'base'),

        // Tema di riferimento da cui ereditano tutti gli altri.
        'base' => 'base',

        // Cartella dei temi (sorgenti, view, lingue). Gli asset compilati stanno in public/themes/{tema}.
        'path' => base_path('themes'),
    ],

];
