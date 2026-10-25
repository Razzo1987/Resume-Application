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

    'themes' => [
        // Tema attivo. Ogni tema eredita dal tema "base" le view e le stringhe che non ridefinisce.
        'active' => env('RESUME_THEME', 'base'),

        // Tema di riferimento da cui ereditano tutti gli altri.
        'base' => 'base',

        // Cartella dei temi (sorgenti, view, lingue). Gli asset compilati stanno in public/themes/{tema}.
        'path' => base_path('themes'),
    ],

];
