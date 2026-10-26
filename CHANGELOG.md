# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Fixed
- CI: the installer tests use the database configured for the test run instead of fixed credentials.
- GitHub Actions updated to `actions/checkout@v5` and `actions/setup-node@v5` (Node.js 24).

## [2.0.3] — 2026-10-26

### Added
- Updates from the admin (*System* → *Updates*): new versions are checked on GitHub at most every 12 hours (and with *Check now*) and announced on the dashboard with their release notes; the package is downloaded straight to the server (in chunks, SHA-256 verified) or uploaded from the browser in 1 MB chunks (no upload limit); checks before starting (version, PHP and extensions, installed themes, disk space, write permissions); content export, maintenance, extraction in short resumable steps by a script that runs before Laravel, with a progress page for the administrator and a static 503 page for visitors; automatic completion afterwards. From 2.0.3 on, no FTP or File Manager is needed to update.
- Optional GitHub token for private repositories (fine-grained, read-only), stored encrypted in `core/storage` or set with `RESUME_UPDATE_TOKEN`; `RESUME_UPDATE_REPOSITORY` and `RESUME_UPDATE_API` settings.
- `core/manifest.json` declares the minimum PHP version and the required extensions.
- Base theme: 52 more icons for interests and key figures (sport and outdoors, free time and culture, work, skills and sectors), among them `cycling` and `ai`.

### Documentation
- New README, installation guide with the release download and checksum (`docs/INSTALL.md`) and configuration guide with a tour of the admin panel, the settings, the icons and the `.env` options (`docs/CONFIGURATION.md`).
- Issue templates, funding link; GitHub releases use the changelog entry as release notes.

## [2.0.2] — 2026-10-26

### Added
- Admin *Translations* page: for each active language, the content written in another language but still empty in this one (CV entries, custom section titles, SEO and footer texts), with a link to edit each item. The "Translations" figures on the dashboard open it.
- "Missing translations" column also in the key figures and spoken languages lists.
- "Go to site" button in the admin top bar, between search and the user menu: opens the public site in a new tab, in the admin language when the site has it (icon only on small screens).

### Changed
- Blog menu entry: a single item, always pointing to the blog page. Whether it is shown, its position and its title come from the "Latest articles" section of the CV layout (*In menu*), even when the section itself is hidden on the CV; it appears only when there are articles in that language. Previously the section added a second "Blog" item pointing to its anchor on the CV page. Existing sites keep the entry (the update turns *In menu* on for that section).
- The theme is chosen only on the *Themes* page; *Settings* shows the active theme with a link to it.
- Plain hyphen instead of en and em dashes in what the site prints: date ranges ("Jan 2021 - present"), CEFR levels ("B2 - Upper intermediate"), page titles ("Name - Headline"); three dots instead of the ellipsis character in excerpts. Same for date ranges in the admin lists.
- Base theme: on wide screens the content is centred in the space next to the sidebar.
- Base theme: blog cover thumbnails keep the 1200 x 630 proportion of sharing images, so titles drawn on a cover are not cropped.

### Fixed
- Importing content exported with an earlier version repeats the data changes made by the updates since then (for example the Blog menu entry above), instead of undoing them.
- Two exports created in the same second (such as the safety copy made before an import) no longer overwrite each other.

## [2.0.1] — 2026-10-25

### Changed
- Links to other sites open in a new tab (screen readers announce it); internal links, email and phone are unchanged.
- The expandable details of education entries (study plans, exams) are not printed.
- Certification badges of any shape: shown in a 6rem-high band, up to 12rem wide for horizontal badges, vertically aligned; same treatment in the admin list. Badges are stored up to 480 px.
- Demo data: an education entry with details and two badges (square and horizontal).

## [2.0.0] — 2026-10-25

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
- Experiences can be moved between work and volunteering from their edit page.
- Site icon (favicon) from the settings: 32×32 and 180×180 versions generated automatically, `/favicon.ico` redirected to it.
- Content import from an export zip (*System* → *Tools* → *Import content*): replaces content, images and CVs, keeps users and the installed version, saves a copy of the current content first, refuses exports from newer versions and unexpected files.

### Changed
- Redirects are matched on the decoded path (old addresses with spaces work) and keep the encoding of the remaining path.
- Project and education facts are laid out in two columns (label, value), also with long values.
- Uploads in the admin up to 50 MB (theme and content zips), within the PHP limits.
- The content export only includes the tables of the application database.
- The menu highlights the section being read with a reading line instead of an intersection band: reliable with short sections and at the bottom of the page.
