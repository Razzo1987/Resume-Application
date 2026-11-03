# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/) and the project uses [Semantic Versioning](https://semver.org/).

## [2.4.0] — 2026-11-03

Updates that are easy to read and signed: one page says what can be updated, where it comes from and what is missing,
and the packages are checked with an Ed25519 signature.

### Added
- **Updates** page (menu *Site*, with a badge when something is to update or to fix): the app and every installed
  theme with installed and latest version, repository, public or private, the GitHub key used and a clear state
  (*Up to date*, *Version x.y.z available*, *A GitHub key is needed*, *The key has no access*, *Key expired or
  revoked*, *Updated from its zip* for themes that do not say where they are published), with the button that solves
  it on the same row. One *Check now* for the app and the themes.
- **GitHub keys** per repository: any number of keys, each for `owner/*`, `owner/repository` or `*`; the most
  specific one is used. *Save and verify* (and *Verify*) asks GitHub whether the key is valid, until when, and which
  repositories in use it can read, then checks the updates again. The expiration date of the keys is read from the
  answers of GitHub; the dashboard warns two weeks before and when a key has expired or was revoked.
- **Signed packages** (Ed25519): every release has `resume-app-X.Y.Z.zip.sig`, made by the release workflow with the
  maintainer's key only for tags signed with the maintainer's GPG key. The app downloads an update only if its
  signature matches the public keys inside the app (a primary and a reserve key); a package uploaded by hand can
  still be installed by an administrator, with a note that its signature was not checked, and automatic updates
  install only signed packages. Themes can declare their keys in `theme.json` (`signing_keys`): their updates are
  then checked with the keys of the installed version.
- **Preview image of the themes** on the *Themes* page (`preview` in `theme.json`, also per variant), served only to
  administrators from the theme folder. The base theme has one.
- **"To translate into..."** filter in the lists of the admin that show the missing translations.
- Release workflow: inspection of the package content (no data, secrets, development files, other themes, symbolic
  links or executable files; `manifest.json` matches the files) and of the extracted files with the privacy guard;
  installation of the package with the web installer on Apache + PHP 8.4, checks of the protected files, of
  `uploads/`, of cookies and headers, and the end-to-end tests of the site on it.
- Dashboard: alerts for theme updates and for components that need a GitHub key.
- The same upload area everywhere (app and theme packages, content import): drop the zip or browse, sent in 1 MB
  chunks with its progress, errors shown in the area itself. *Upload a package* on the *Updates* page accepts the app
  package (checked before the installation) and packages with themes (installed right away).

### Changed
- The update of the app (new version, packages on the server, automatic updates) moved from *System* to *Updates*;
  *System* keeps a summary with the state of every component. The update emails point to the *Updates* page.
- Public repositories never get a key: an expired or revoked key can no longer stop the check of the app updates (a
  key refused by GitHub is retried without it on public repositories).
- Content import: the export is uploaded in chunks, so the hosting upload limit no longer matters (the hint to copy
  bigger files by FTP is gone); the uploaded file appears already chosen in the list of the exports on the server,
  and an existing export with the same name is never overwritten.
- The single GitHub token of the previous versions becomes a key for every repository, with nothing to do.
- Problems reported by GitHub are saved as codes and shown in the language of who reads them.

### Fixed
- `htaccess.dist` in the web root was readable from the browser (found by the new Apache test): it is now denied like
  the hidden files (the `.htaccess` block is rewritten by the update).
- Release packages: files have no executable bit any more (only `core/artisan`).
- Statistics: without filters, the *Days and hours* grid (and the rankings of channels and of system and browser
  versions) showed only the days after the update to 2.3, because the daily totals of the days before had no hours,
  channels or versions; with filters, which read the single visits, they were complete. The totals of the days still
  kept are recalculated by themselves, a month at a time, when the *Statistics* page opens and from the cron job (a
  note on the page says so while it runs). Days whose single visits have already been deleted keep their totals.
- Statistics: the columns of the *Days and hours* grid have the same width (the hour labels made some wider).

## [2.3.1] — 2026-11-02

Security fixes. None of them can be used by a visitor: they all need an administrator (or a file an administrator is
led to use), but they close the ways in which an action that does not look like "installing code" could become code.

### Security
- Images that cannot be converted (animated GIFs, unusual files) are saved with the extension of their real type,
  never the one of the uploaded file name: an animated GIF called `x.html` could become a page of the site.
- Importing a content export accepts only images and PDFs in `uploads/` and only PDFs among the CVs (no `.php`,
  `.html` or other files in the web root); hidden files such as `.htaccess` are skipped, and exports no longer
  contain them.
- `uploads/` gets its own `.htaccess` (installer, every update, every import): only images and PDFs are served and
  nothing in the folder can run as code. Example rule for nginx in docs/INSTALL.md.
- Updates of the app and of the themes require the SHA-256 file published with the release: without it the package
  is not downloaded.
- Installer: with a database that is not on `localhost` it asks for an installation key, written in
  `core/storage/install-key.txt` (only the owner of the hosting account can open it) and deleted at the end.
- Admin pages: `X-Frame-Options`/`frame-ancestors` (never inside a frame of another site), `nosniff`,
  `Referrer-Policy`, `Permissions-Policy`, HSTS on HTTPS.
- Page cache: in production only the pages of the `APP_URL` domain are cached (requests with invented host names no
  longer add files).
- Pages not found: at most 5,000 different paths are kept, so a scanner cannot fill the database.
- Dashboard: a reminder to turn on two-step verification, as long as it is off, explaining why it matters.
- symfony/mime 8.1.10 (CVE-2026-45070).

### Added
- SECURITY.md: supported versions, private reporting, threat model.
- CI: secrets (gitleaks), privacy guard, `composer audit`, `npm audit`, npm lockfile check, actionlint and zizmor;
  actions pinned to a commit SHA, read-only token. Release: signed tag on `main`, the whole CI before building,
  build and publication in separate jobs, provenance attestation, release published only when complete.
  Dependabot for actions, Composer and npm.

## [2.3.0] — 2026-10-31

### Added
- Statistics: **visitors and visits (sessions) without cookies, for 24 hours at most**. Each page view stores two keyed hashes (HMAC-SHA256) of the IP address and of IP address + browser, with a random salt created every day and kept for two days at most; a page view joins a visitor first seen less than 24 hours ago, and the same visit if the previous page was less than 30 minutes ago. After 24 hours the hashes are removed (also without the cron job) and only random visitor and visit codes remain. New figures: visitors, visits, pages per visit, average duration, bounce rate; for a single page visitors, entrances and exits. Visits recorded before 2.3 have no visitor or visit.
- Statistics: **exclusions** page with three statuses: *Admin (automatic)* (the public address used for the admin, it expires as before), *Excluded* (IP address, network as shortened address, or provider/organization by ASN) and *Always count* (a shared office network). Adding an exclusion deletes the visits already recorded, after showing how many (IP address: last 24 hours; network and ASN: all those kept) and recalculates the daily totals; removing it starts the count again, nothing is restored. *Exclude the network* on every row of the latest visits.
- Statistics: when an administrator opens the admin, the visits of the last 24 hours from the same address and browser are deleted (those made just before signing in).
- Statistics: days and hours (grid of the week in the time zone of the site), entry and exit pages, channels (direct, search engines, social networks, AI assistants, email, other sites, campaigns; also as a filter), organizations apart from connection providers and data centers (built-in lists, correctable from the page), operating system and browser versions, icons of systems, browsers, devices, channels and sites (Tabler Icons, MIT).
- Statistics: **bots and crawlers** counted by name and day without any address (search engines, AI crawlers such as GPTBot, ClaudeBot and PerplexityBot, SEO crawlers, monitoring, social previews, feed readers, scripts).
- Statistics: **pages not found** by path, with the requests from people, the last one and the referring site; attack attempts (`wp-login.php`, `.env`...) counted apart; *Create redirect* from the list.
- Statistics: the world map zooms in on a country with a click (more detailed borders, 1:50m) and shows only its visits; a click on a city filters by city; *World* goes back. Countries and cities can be reached with the keyboard.

### Changed
- The excluded admin IPs (`stat_admin_ips`) become automatic entries of the exclusions (`stat_exclusions`).
- Privacy page (9 languages): the statistics section, added by itself when statistics are on, explains the 24-hour code instead of "I do not recognise people who come back".
- Bots, scripts and feed readers are no longer simply ignored: they are counted apart, never as visits.

## [2.2.0] — 2026-10-29

### Added
- Theme variants: a theme can offer several palettes and fonts (`variants` in theme.json), chosen on the *Themes* page without installing anything else.
- Theme packages with several themes, each in its own folder: installed or updated in one go, refused as a whole if one of them is not valid.
- Theme packages are uploaded in 1 MB chunks, like the update packages: the upload limit of the hosting (often 2 MB) no longer matters.
- The menu highlight of the base theme leaves alone menus that a theme turns into tabs (`role="tablist"`).
- Theme updates from GitHub (`updates` in theme.json, also from private repositories with the GitHub token of the System page): new scheduled job *New theme versions*, *Check for theme updates* and *Update to X.Y.Z* on the *Themes* page, one email per theme and version.
- Tabler Icons (MIT) in the icon sprite builder (`ti ti-briefcase`, `ti ti-heart-filled`), as an alternative to Font Awesome.
- docs/THEMES.md: folder, theme.json, variants, icons, packages and updates of a theme.
- Character counters on SEO titles and descriptions (site, projects, experience) and on article excerpts ("142 / 160 characters"), in orange beyond the length that search results show. The fields themselves accept longer texts.
- *System* → *Tools* → *Send a test email*: sends a message to the signed-in administrator and shows the sending method and sender, to check that the server delivers the emails (update notices, password reset).
- Email to the administrators when a new version is available and automatic updates are off (one per version; with *Download and notify* the email arrives when the package is on the server).

### Changed
- With the cron job, new versions are checked at every run of the updates job (every 30 minutes) instead of every 12 hours, with conditional requests (ETag) that do not count against the GitHub limit. Without the cron job the check stays at most every 12 hours. Before, a release could be noticed up to 12 hours late.
- SEO titles accept up to 120 characters (about 60 are shown by search results); SEO descriptions of experience up to 300.
- The GitHub token of the System page is described as the token for private repositories (PRO themes) and for a higher request limit.
- Statistics: the home page is called "Home page (CV)" and the CV downloads "CV download (PDF)", so that they are not mistaken for each other. In the English admin and in the documentation the menu group of the CV content is called "CV" instead of "Résumé", like the rest of the admin (the American English pack of the public site keeps "résumé").

### Fixed
- Scheduled jobs: the result of the statistics job no longer reads "totals of 1 days" (no plurals to get wrong), and the GeoLite job says that the MaxMind account is missing instead of "off" when statistics are on.
- Update emails sent by the cron job showed the text keys (`admin.updates.auto.mail.downloaded`) instead of the message: the cron job has no admin language. Each administrator now gets the email in the admin language chosen in the profile, and the results of the scheduled jobs are written in the admin language too.
- Update emails are clearer: they say that the site has not been changed yet, which button to press on the System page and where to read what is new.
- Update emails that the server could not send are recorded in the log instead of being ignored silently.

## [2.1.1] — 2026-10-28

### Fixed
- Public site: a social link of type "Website" without a label of its own showed `admin.enums.social.website` instead of "Website" in the page language (new key `contact.website` in the language packs).
- Admin: validation errors showed the raw key (`validation.max.string`) with the admin in Italian: the Laravel messages (validation, password reset, sign-in) are now translated (from Laravel Lang, MIT); the installer benefits too.
- Admin: SEO descriptions (site and projects) accept up to 300 characters, with a note that search results show about 160; before, 170 was the limit, so settings imported with a longer text could not be saved. Short descriptions of interests accept up to 300 characters too.
- Statistics: the note about an untrusted proxy also appeared on hostings that already give the site the visitor's address and pass the same address in `X-Forwarded-For`; now it only appears when the header names a different client.
- Admin: *Go to site* opens the site in the language of the administrator (profile choice), not in the one of `RESUME_ADMIN_LOCALE`.

## [2.1.0] — 2026-10-27

### Added
- Scheduled jobs: one cron job in the hosting control panel (`php …/core/artisan schedule:run` every 5 minutes, the exact line is on the *System* page) runs the update check, automatic updates and clean-up; without it the light jobs start from the admin at most once an hour. The *System* page shows whether the cron job runs, with which PHP version, and the result of every job. Commands `resume:cron` and `resume:update --check|--finish`.
- Visit statistics without cookies (*Statistics*, off by default): page views and CV downloads with time, page, referring site, campaign parameters (UTM; click IDs such as `fbclid` only as "from Facebook"), device, operating system, browser, language, and country, city and network from the IP address. The IP address is shortened straight away (93.44.12.x, IPv6 to 48 bits) and never stored in full; bots, scripts, previews and the administrator do not count (the browser signed in to the admin, whatever the address, and the public IP used for the admin, for 14 days after the last access, configurable). Private and local addresses are never excluded as admin IPs: behind a proxy that gives everybody the same one (GitHub Codespaces port forwarding) every visit was being discarded; the *Statistics* page now explains when the visitor address is not available or the proxy is not trusted. Recorded after the response, also for pages served from the HTML cache. Daily totals per page and dimension stay after single visits are deleted (12 months, configurable). The page shows totals, visits over time, most viewed pages, sources, campaigns, a world map drawn on the server (Equal Earth, no external tiles), countries, cities, networks, devices, systems, browsers, shortened IP addresses and the latest visits. Filters by period and page, and by referring site, UTM source, medium and campaign, platform click, country, city, network, device, system, browser, language and shortened IP, combined and kept in the page address; a click on a ranking entry or on the IP of a visit sets the filter. Selected visits can be deleted (the daily totals are recalculated). Statistics are not part of content exports.
- Location from the free MaxMind GeoLite2 City and ASN databases installed on the server (the IP address is not sent anywhere): MaxMind Account ID and License Key entered in the admin (stored encrypted) or in `.env`; download in chunks from the admin and daily update by the cron job, checked with `Last-Modified` like `geoipupdate`.
- Admin language per administrator: chosen in the profile (Italiano, English: every language with a `lang/{code}/admin.php`); without a choice, and on the sign-in page, `RESUME_ADMIN_LOCALE` applies.
- Privacy notice: page `/{language}/privacy` (segment translated in every language pack) with a link in the footer, turned on from *Settings* and always on with visit statistics. The text is written per language in the admin, starting from a template in the 9 languages (`{name}` and `{email}`, encoded as in the rest of the site); without a text of its own a language shows the template. With statistics on, a section describing them is added from the actual settings. The page is `noindex`.
- Automatic updates (*System* → *Updates*): off, download and notify (default: the package is already on the server, one click to install) or install fix releases by themselves (2.1.0 → 2.1.1; feature releases are only downloaded). Same checks as from the admin, plus the PHP version and extensions of the web server; content export first; extraction by the cron job and completion in a new process; result on the dashboard and emailed to the administrators, with a link to continue from the browser if something stops.
- `RESUME_TRUSTED_PROXIES`: sites behind a proxy that changes the request address (CDN or tunnel such as Cloudflare, GitHub Codespaces) read address, protocol and visitor IP from the `X-Forwarded-*` headers. Before, the return address after signing in could point to `localhost`.
- Try it in the browser: GitHub Codespaces configuration (`.devcontainer/`) with PHP 8.4, MySQL 8 and the demo CV, ready in a few minutes with a demo administrator (`demo@example.com` / `demo-password`).
- Screenshots of the site and of the admin panel in the README and in `docs/SCREENSHOTS.md`, generated by a Playwright script (`tests/e2e/screenshots.spec.js`, run with `SCREENSHOTS=1`).
- CI: the end-to-end job publishes the HTML test report and fresh screenshots as workflow artifacts; CD badge for the release workflow in the README.
- Time zone of the site (*Settings*), proposed from the country of the default language: it sets the day of the visit statistics, the dates of articles and of "Last updated on", and the dates and times in the admin. Before, everything used UTC, so a visit at 00:30 in Italy counted for the day before.
- Missing translations in *Profile* and *Settings*: each language tab shows how many texts are still empty, with their names on hover; the profile also lists them at the top of *Presentation*.
- Accessibility checks (axe, WCAG 2.1 AA) on the privacy page and on the admin pages with markup of their own (dashboard, statistics, system, translations, profile, settings). The demo CV has the privacy page on.

### Changed
- Dashboard grouped by type, each card linking to where you act: *System* (version and available update, cron job, automatic updates, last content export, warning after a month), *Languages* (site languages, missing translations), *Content* (work experience and volunteering, projects with a page of their own, published, draft and scheduled articles, active CV sections hidden because empty), *CV* (PDF per language with its date, what visitors get when it is missing) and *Statistics* (views and CV downloads of the last 7 days with a sparkline and the change on the 7 days before, most viewed page, main source and country).
- Hosting: texts and guides no longer assume cPanel. Any panel works (Apache with `.htaccess`); the installation guide has a table of the menus of the common panels and an example of rules for nginx alone, and the *System* page finds the command line PHP of Plesk and DirectAdmin too.
- `zlib` is declared among the required PHP extensions (installer, update packages, documentation): the GeoLite download and the packages already needed it.
- Code comments, CI step names and the messages of the development scripts and of the `resume:*` commands are in English; the few error messages that were written in the code (file that cannot be written, missing zip extension, image that cannot be resized) now come from the language files.
- Sample CV: "John Doe" is now a generic full-stack developer and tech lead (web projects for a transport company, accessibility, coding mentoring, a blog article about accessible websites) instead of a data governance profile; screenshots updated.

### Fixed
- Codespaces: the container build failed because of the expired key of the Yarn apt repository included in the base image; the setup now waits for the database with PHP instead of the MySQL client. The web server now runs in a terminal of its own (`postAttachCommand`): started in the background by `postStartCommand` it was stopped as soon as the command ended, so port 8000 did not answer. After signing in to the admin you were sent to `http://localhost:8000`: the Codespace now trusts its port forwarding proxy (`RESUME_TRUSTED_PROXIES`, see above); port 8000 is made public automatically with the GitHub CLI; Xdebug is off (it printed a warning at every command).
- CI: the installer tests use the database configured for the test run instead of fixed credentials.
- PHPStan analyses the update script too (`resources/update/updater.php`), so its functions are always known: the analysis failed on GitHub depending on how files were split between the parallel workers.
- GitHub Actions updated to versions that run on Node.js 24: `actions/checkout@v5`, `actions/setup-node@v5`, `actions/upload-artifact@v7`, `softprops/action-gh-release@v3`.
- Tests: two statistics tests used the UTC date instead of the date of the site time zone and failed when run just after local midnight; test fixtures no longer create folders that already exist (PHPUnit warnings).

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
- Managed `.htaccess` block (`# BEGIN resume-app` … `# END resume-app`) that leaves the rules added by the hosting panel untouched; one-year immutable caching for compiled assets and uploads, compression including RSS.
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
