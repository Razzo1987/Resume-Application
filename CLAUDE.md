# Resume App — guidelines for AI assistants and contributors

## Stack
- Laravel 13, Filament 5 (Livewire 4), PHP 8.4 minimum (dependencies are resolved with `config.platform.php = 8.4.1`), tested on 8.4 and 8.5.
- MySQL 8.0 in production and in tests (`resume_app_test`), not SQLite.
- Frontend: Blade views inside themes, Bootstrap 5.3 compiled from SCSS, vanilla JS, Vite. No jQuery, no CDN.

## Non-negotiable rules
1. **No cookies on the public site.** Public routes live in `routes/site.php` under the `site` middleware group (no session, no CSRF, `StripCookies`). Never add public routes to `routes/web.php`.
2. **Everything self-hosted.** No external fonts, scripts, styles or images (no Google Fonts, Bunny Fonts, CDNs). Tests check that pages only reference local assets.
3. **Translatable content** is stored once per entity, with JSON columns for the translated text fields; dates, links and images are never duplicated per language.
4. **Language codes** are full locale codes (`it-IT`, `en-GB`) and are also URL prefixes. Language packs live in `lang/{code}/routes.php` and `lang/{code}/site.php`; keep keys identical across languages (a test enforces it).
5. **Themes** extend `base`. Views are resolved with the `theme::` namespace (active theme first, then `base`). Icons are semantic names mapped per theme in `icons.php`; use `<x-theme-icon name="…"/>`.
6. **Deployment layout**: release packages put `public/` at the web root and everything else in `core/` (denied by `.htaccess`). Do not rely on `base_path()` being outside the web root; never write secrets or user data under `public/` except uploaded images in `public/uploads`.
7. Personal data (email, phone, birth date) is never emitted in JSON-LD unless the administrator explicitly enables it.

## Before committing
```bash
php artisan test
vendor/bin/phpstan analyse
vendor/bin/pint --test
```
