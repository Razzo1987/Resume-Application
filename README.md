# Resume App

A multilingual CV / résumé web application with a lightweight admin panel, built with **Laravel** and **Filament**.
It runs on any standard LAMP hosting (including cPanel shared hosting), uses **no cookies** on the public site and loads **no third-party resources** (fonts, scripts and styles are all self-hosted).

> Status: **v2 release candidate** — admin panel, public site with the open `base` theme, blog, web installer and update page are in place (milestones M0–M4).

## Features (v2.0)

- CV sections: profile, key figures, experience, volunteering, projects, education, certifications, languages (CEFR), publications & talks, interests — each one can be enabled, disabled and reordered from the admin.
- Dedicated, SEO-friendly pages for projects, experiences and blog articles; `canonical`, `hreflang`, Schema.org JSON-LD, sitemap and RSS.
- Any number of languages, enabled from the admin, with translated URLs (language packs for it-IT, en-GB, en-US, de-DE, fr-FR, es-ES, pt-PT, pt-BR, nl-NL).
- Versioned CV PDF per language, downloaded with a stable file name.
- Mini blog.
- Theme system: an open `base` theme (Bootstrap 5 + Font Awesome Free) that other themes can extend.
- Full-package releases with a web installer and an update page (database migrations, cache, file clean-up).

## Requirements

- PHP **8.4 or 8.5** with `intl`, `mbstring`, `pdo_mysql`, `gd`, `zip`, `fileinfo`, `dom`, `openssl`
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
npx playwright test                 # end-to-end + accessibility (needs the demo data and built assets)
```

## Public site

- `/` redirects to the default language; every page lives under its language code (`/it-IT`, `/en-GB/projects/{slug}`).
  Wrong casing, trailing slashes, segments or slugs of another language are redirected (301) to the canonical address.
- Translated URL segments come from the language packs and can be changed per language in the admin;
  when a slug or a segment changes, the old address keeps working with a permanent redirect.
- Blog at `/{language}/blog` with an RSS feed at `/{language}/feed.xml`; an article is listed only in the languages it is written in.
- Projects and experiences get a dedicated page only when there is something more than the CV summary
  (a detail text, an image, or related projects), so search engines never see empty pages.
- SEO: `canonical`, `hreflang` + `x-default`, Open Graph, sitemap with language alternates, Schema.org JSON-LD
  (`ProfilePage` + `Person`, publications, breadcrumbs). Email, phone and birth date are left out of JSON-LD unless enabled.
- Privacy: no cookies, no third-party requests, strict Content Security Policy; email and phone are encoded in the HTML
  and shown only by the theme script.
- Performance: HTML page cache (cleared on every change), ETags, a reduced Bootstrap build and an SVG icon sprite
  with only the icons the theme uses.

## Themes

A theme lives in `themes/{name}` with a `theme.json`, Blade views (`views/`), an icon map (`icons.php`) and its assets.
Themes extend `base`: a view that a theme does not define is taken from `base`, so a theme can override a single
partial (`views/partials/profile-headlines.blade.php`) and nothing else, and reuse the base SCSS partials and
JavaScript behaviours. Icons are semantic names (`briefcase`, `email`, …) mapped to the theme's icon set;
`scripts/build-icons.mjs` builds an SVG sprite with only the mapped icons (Font Awesome 5, 6 or 7 classes, one file
per icon or one sprite per style as source; inherited icons missing from the source fall back to Font Awesome Free).

Other themes (for example commercial ones) are developed **outside this repository** and never committed here
(`themes/*` except `base` is ignored by git):

```bash
scripts/build-theme.sh ../my-themes/fancy --icons ../my-themes/fancy/icons --zip ../dist   # copy, icons, assets, zip
E2E_THEME=fancy npx playwright test       # general end-to-end suite with that theme + the theme's own tests
```

The zip is installed from the admin (**Themes** → *Install or update a theme*): `theme.json`, views and language
files go to `core/themes/{name}`, the compiled assets (`dist/`) to `themes/{name}` in the web root. The package is
validated before anything is written (allowed file types only, no PHP among the public assets, compatible
`requires.resume-app` version, parent theme installed). If the active theme or its assets go missing, the site falls
back to `base`.

## Project layout

| Path | Content |
|---|---|
| `app/Models` | CV content; translated fields are JSON columns (`{"it-IT": "…", "en-GB": "…"}`) |
| `app/Filament` | Admin panel: resources, profile and settings pages, dashboard widget |
| `app/Support` | Languages and language packs, settings, translations, image optimisation (WebP), installer, system tools (maintenance, updates, backup) |
| `app/Site` | Public site: URLs, page metadata, JSON-LD, CV data loader, page cache, redirects |
| `app/Themes` | Theme manager (active theme → `base` inheritance, icon map), theme installer and version constraints |
| `themes/base` | Open theme: views, icons, SCSS/JS sources (built into `public/themes/base`) |
| `lang/{code}` | Language packs: `routes.php` (URL segments) and `site.php` (public site strings) |
| `routes/site.php` | Public routes — no session, no CSRF, no cookies |
| `tests/e2e` | Playwright tests (Chromium, Firefox, WebKit, mobile) with axe accessibility checks |
| `stubs/` | `.htaccess` blocks used by release packages |
| `scripts/build-release.sh` | Builds the full release zip |
| `scripts/build-theme.sh` | Builds an additional theme and its installable zip |

## Deployment (release package)

The release zip (`scripts/build-release.sh`) is extracted **entirely inside the web root** (e.g. `public_html/`)
and installed from the browser at `/install`:

```
public_html/
├── index.php, build/, themes/   public files
├── uploads/                     uploaded images (never part of a package)
└── core/                        application code, denied to browsers by .htaccess
```

Updates are full packages extracted over the previous one, completed from the admin *System* page
(migrations, caches, `.htaccess`, clean-up of files no longer used). See **[docs/INSTALL.md](docs/INSTALL.md)**.

If your hosting allows it, `core/` can live outside the web root: create `public_html/core-path.php` returning its absolute path.

## License

MIT — see [LICENSE](LICENSE). Font Awesome Free icons: CC BY 4.0, fonts: SIL OFL 1.1.
