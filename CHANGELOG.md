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
