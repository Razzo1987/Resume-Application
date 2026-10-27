<?php

/*
 * Resume App — base configuration.
 *
 * These values are the installation defaults. From phase M1 on, the active theme,
 * the languages and the other preferences are managed from the admin panel (settings table).
 */

return [

    // Path of the administration panel (e.g. "admin" → https://sito/admin).
    'admin_path' => env('RESUME_ADMIN_PATH', 'admin'),

    // Language of the admin interface (Filament translations: "it", "en", ...).
    'admin_locale' => env('RESUME_ADMIN_LOCALE', 'it'),

    // Page of the open source project ("Realizzato con Resume App" link in the footer).
    'project_url' => 'https://github.com/Razzo1987/Resume-Application',

    // HTML cache of the public pages (can be disabled in development with RESUME_PAGE_CACHE=false).
    'page_cache' => (bool) env('RESUME_PAGE_CACHE', true),

    // Installer: path of the .env that marks the installation (empty = Laravel's one). Only used by the tests.
    'installer' => [
        'env_path' => null,
    ],

    // Updates: new versions from the GitHub repository releases (vX.Y.Z tag with the package zip).
    // The token is only needed if the repository is private: it is usually entered in the admin (System page),
    // but this value takes precedence.
    'updates' => [
        'repository' => env('RESUME_UPDATE_REPOSITORY', 'Razzo1987/Resume-Application'),
        'api' => env('RESUME_UPDATE_API', 'https://api.github.com'),
        'token' => env('RESUME_UPDATE_TOKEN'),
        'check_every_hours' => 12,
    ],

    // Origin of the visits (statistics): MaxMind GeoLite2 databases downloaded with a free account.
    // Account ID and License Key are usually entered in the admin (Statistics page); these values take precedence.
    'geoip' => [
        'account_id' => env('RESUME_GEOIP_ACCOUNT_ID'),
        'license_key' => env('RESUME_GEOIP_LICENSE_KEY'),
        'download_url' => env('RESUME_GEOIP_DOWNLOAD_URL', 'https://download.maxmind.com/geoip/databases/{edition}/download?suffix=tar.gz'),
    ],

    'themes' => [
        // Active theme. Every theme inherits the views and strings it does not redefine from the "base" theme.
        'active' => env('RESUME_THEME', 'base'),

        // Reference theme that all the others inherit from.
        'base' => 'base',

        // Themes folder (sources, views, languages). The compiled assets are in public/themes/{theme}.
        'path' => base_path('themes'),
    ],

];
