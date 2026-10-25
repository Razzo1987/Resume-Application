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
