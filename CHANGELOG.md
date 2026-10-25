# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased] — 2.0.0

### Added
- New foundation on Laravel 13 and Filament 5 (PHP 8.4 / 8.5).
- Admin panel at a configurable path (`RESUME_ADMIN_PATH`), Italian interface by default, self-hosted fonts.
- Public routes without session, CSRF or cookies, with a safety net that strips any cookie.
- Theme manager with inheritance from the `base` theme and semantic icons.
- Language packs for 9 languages (URL segments) and public site strings for it-IT, en-GB, en-US.
- Front controller that works with the release layout (`core/` inside the web root), the standard Laravel layout and an external core (`core-path.php`).
- `.htaccess` blocks that deny access to the application code; release packaging script with SHA-256 manifest.
- Tests (Pest), static analysis (Larastan level 6), code style (Pint), GitHub Actions CI on PHP 8.4 and 8.5.
- Data model for the whole CV: profile, social profiles, organisations, experience and volunteering, projects, skills, education, certifications, languages spoken (CEFR), publications and talks, interests, key figures (manual or computed, exact or "10+"), CV layout (sections can be enabled, disabled and reordered), blog articles, redirects.
- Translated fields stored once per entity as JSON, with per-language slugs generated automatically and a fallback to the default language.
- Site languages managed from the admin: default language, active languages, URL segment overrides and custom site strings per language.
- Versioned CV PDF per language, stored privately with a timestamped name (`cv_it-IT_20261025-101500.pdf`), downloaded with a configurable stable name.
- Admin panel: one tab per active language in every form, translation status on the dashboard, settings page (theme, CV file name, print version, "last updated" footer, contact visibility, personal data in JSON-LD — off by default), profile page, two-factor authentication with recovery codes.
- Uploaded images are resized and converted to WebP; the admin avatar is generated locally (no ui-avatars.com).
- Demo seeder with a bilingual sample CV.
- Public site with the open `base` theme: CV page with the sections in the configured order, dedicated pages for projects and experiences, language switcher to the same page, themed 404 page.
- Multilingual routing with translated URL segments, canonical redirects (language code casing, trailing slashes, segments and slugs of other languages), redirects managed in the admin (also for sub-paths) and created automatically when a slug or a segment changes.
- SEO: canonical, hreflang with x-default, Open Graph (with a dedicated sharing image), sitemap.xml with language alternates, robots.txt, Schema.org JSON-LD without personal contact data unless enabled.
- CV download at `/{language}/{cv segment}` with the standard file name, ETag and optional fallback to the default language.
- Email and phone encoded in the HTML and revealed by the theme script; print stylesheet and optional "Print" button; optional "last updated" date.
- HTML page cache cleared on every change, strict Content Security Policy and security headers, `noindex` for the admin.
- Base theme: reduced Bootstrap build, SVG icon sprite generated from the theme icon map (Font Awesome Free), no web fonts, WCAG 2.1 AA (axe), Lighthouse 100 on the demo data.
- Site strings for de-DE, fr-FR, es-ES, pt-PT, pt-BR, nl-NL (drafts to be reviewed by native speakers); en-US uses "résumé".
- End-to-end tests with Playwright (Chromium, Firefox, WebKit, mobile) in CI.
- Blog: paginated list per language, article page with `BlogPosting` data, RSS feed per language (`/{language}/feed.xml`), sitemap entries, "latest articles" CV section, blog entry in the menu. An article appears only in the languages it is written in; scheduled publication is respected by the page cache.
- Admin: "View on site" on projects, experiences and articles that have a public page.
- Web installer at `/install`, active only while `core/.env` does not exist and without session or cookies: requirement checks, refusal to continue when the server does not apply `.htaccess` files, database, administrator, languages, optional demo CV, admin path, HTTPS and "without www" redirects; `.env` written last with `0600` permissions. An existing Resume App database is kept (useful to recover a lost `.env` or password).
- Admin *System* page: version and status, "complete the update" (migrations, caches, `.htaccess` block, recorded version), maintenance mode for the public site only (HTTP 503 with `Retry-After`, admin still available), check of the `core/` protection, `.htaccess` rules, removal of files no longer part of the package (by manifest) and of the folders left empty, content export (zip without users and secrets).
- Dashboard alerts for an update to be completed, maintenance mode and an exposed `core/` folder.
- Managed `.htaccess` block (`# BEGIN resume-app` … `# END resume-app`) that leaves the rules added by cPanel untouched; one-year immutable caching for compiled assets and uploads, compression including RSS.
- Release package pruned of vendor tests and docs; installation and update guide (`docs/INSTALL.md`).
- Admin *Themes* page: list of installed themes, activation, installation or update from a zip (validated before writing: allowed file types only, no PHP among public assets, version constraint, parent theme), removal of unused themes. The site falls back to the base theme when the active theme or its compiled assets are missing.
- Theme tooling: `scripts/build-theme.sh` (copy, icon sprite, assets, zip), Vite build per theme (`THEME=name`), icon sprites from Font Awesome 5 classes (`fas`, `fal`, `fad`…) and per-style sprite sources with duotone support, `theme_color` in `theme.json`, `partials/head-extra` and `partials/profile-headlines` extension points, base JavaScript behaviours reusable by other themes, end-to-end suite runnable against another theme (`E2E_THEME`).
- The inline flag script also marks navigations inside the site (`js-same-site`), so themes do not replay intro animations.

- `php artisan resume:import-v1`: imports the content of version 1 (one row per language, pairs by id) — profile, social profiles, experience and volunteering with organisations, logos and skills, projects linked to the experience of their dates and to their client, education, certifications with badges and PDFs, current CV, footer texts — and redirects the old addresses of PDFs and CVs.
- Content import from an export zip (*System* → *Tools* → *Import content*): replaces content, images and CVs, keeps users and the installed version, saves a copy of the current content first, refuses exports from newer versions and unexpected files.

### Changed
- Redirects are matched on the decoded path (old addresses with spaces work) and keep the encoding of the remaining path.
- Project and education facts are laid out in two columns (label, value), also with long values.
- Uploads in the admin up to 50 MB (theme and content zips), within the PHP limits.
- The content export only includes the tables of the application database.
- The menu highlights the section being read with a reading line instead of an intersection band: reliable with short sections and at the bottom of the page.
