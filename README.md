# Resume App

A multilingual CV / résumé web application with a lightweight admin panel, built with **Laravel** and **Filament**.
It runs on any standard LAMP hosting (including cPanel shared hosting), uses **no cookies** on the public site and loads **no third-party resources** (fonts, scripts and styles are all self-hosted).

> Status: **v2 in development** — data model and admin panel are in place (milestones M0–M1); the public site comes next.

## Features (planned for v2.0)

- CV sections: profile, key figures, experience, volunteering, projects, education, certifications, languages (CEFR), publications & talks, interests — each one can be enabled, disabled and reordered from the admin.
- Dedicated, SEO-friendly pages for projects, experiences and blog articles; `canonical`, `hreflang`, Schema.org JSON-LD, sitemap and RSS.
- Any number of languages, enabled from the admin, with translated URLs (language packs for it-IT, en-GB, en-US, de-DE, fr-FR, es-ES, pt-PT, pt-BR, nl-NL).
- Versioned CV PDF per language, downloaded with a stable file name.
- Mini blog.
- Theme system: an open `base` theme (Bootstrap 5 + Font Awesome Free) that other themes can extend.
- Full-package releases with a web installer and an update page (database migrations, cache, file clean-up).

## Requirements

- PHP **8.4 or 8.5** with `intl`, `mbstring`, `pdo_mysql`, `gd`, `zip`, `fileinfo`, `bcmath`
- MySQL 8.0+ (or MariaDB 10.11+)
- Apache with `mod_rewrite` (`.htaccess` support)

## Development

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed          # base data: default languages, CV sections, key figures
php artisan db:seed --class=DemoSeeder   # optional: sample CV of "John Doe" (it-IT / en-GB)
npm install && npm run build
php artisan make:filament-user      # first administrator
php artisan serve                   # http://localhost:8000 — admin at /admin
```

Quality checks:

```bash
php artisan test                    # Pest
vendor/bin/phpstan analyse          # Larastan
vendor/bin/pint --test              # code style
```

## Project layout

| Path | Content |
|---|---|
| `app/Models` | CV content; translated fields are JSON columns (`{"it-IT": "…", "en-GB": "…"}`) |
| `app/Filament` | Admin panel: resources, profile and settings pages, dashboard widget |
| `app/Support` | Languages and language packs, settings, translations, image optimisation (WebP) |
| `app/Themes` | Theme manager (active theme → `base` inheritance, icon map) |
| `themes/base` | Open theme: views, icons, SCSS/JS sources (built into `public/themes/base`) |
| `lang/{code}` | Language packs: `routes.php` (URL segments) and `site.php` (public site strings) |
| `routes/site.php` | Public routes — no session, no CSRF, no cookies |
| `stubs/` | `.htaccess` blocks used by release packages |
| `scripts/build-release.sh` | Builds the full release zip |

## Deployment (release package)

The release zip is extracted **entirely inside the web root** (e.g. `public_html/`):

```
public_html/
├── index.php, build/, themes/   public files
├── uploads/                     uploaded images (never part of a package)
└── core/                        application code, denied to browsers by .htaccess
```

If your hosting allows it, `core/` can live outside the web root: create `public_html/core-path.php` returning its absolute path.

## License

MIT — see [LICENSE](LICENSE). Font Awesome Free icons: CC BY 4.0, fonts: SIL OFL 1.1.
