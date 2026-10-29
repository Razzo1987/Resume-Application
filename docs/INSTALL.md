# Installing and updating Resume App

This guide covers a standard shared hosting account managed from a **control panel**; any Apache + PHP + MySQL server
works the same way. The panels give the same tools different names (see [Where to find it](#where-to-find-it-in-the-control-panel)).
Once the site is installed, [CONFIGURATION.md](CONFIGURATION.md) explains how to set it up from the admin panel.

- [Requirements](#requirements)
- [Getting the package](#getting-the-package)
- [Package layout](#package-layout)
- [First installation](#first-installation)
- [Updating](#updating)
- [Moving the content](#moving-the-content)
- [Themes](#themes)
- [The `.htaccess` file](#the-htaccess-file)
- [Troubleshooting](#troubleshooting)

## Requirements

| | |
|---|---|
| PHP | 8.4 or 8.5 with `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `zip`, `zlib` |
| Database | MySQL 8.0+ or MariaDB 10.11+ |
| Web server | Apache with `mod_rewrite` and `.htaccess` files enabled (`AllowOverride All`); LiteSpeed reads the same files (not tested yet); for nginx alone see [below](#without-htaccess-nginx) |
| Recommended | `mod_headers`, `mod_deflate`, an SSL certificate (AutoSSL / Let's Encrypt) |

The installer checks all of them before doing anything. Nothing else is needed on the server: no SSH, Composer or
Node.js (the package already contains the PHP dependencies and the compiled assets).

## Getting the package

Every version is published on the [GitHub releases page](https://github.com/Razzo1987/Resume-Application/releases)
with two files:

| File | Content |
|---|---|
| `resume-app-x.y.z.zip` | the complete application, ready to extract (about 26 MB) |
| `resume-app-x.y.z.zip.sha256` | its SHA-256 checksum |

To check that the download is complete and unchanged, compare the checksum with the one in the `.sha256` file
(also shown at the end of the release notes):

```bash
sha256sum resume-app-x.y.z.zip                      # Linux
shasum -a 256 resume-app-x.y.z.zip                  # macOS
certutil -hashfile resume-app-x.y.z.zip SHA256      # Windows (Command Prompt)
```

Do not use the "Source code" archives that GitHub adds to every release: they contain the sources, without the
dependencies and the compiled assets, and are meant for development.

## Package layout

The release zip (`resume-app-<version>.zip`) is extracted **entirely inside the web root**:

```
public_html/
├── index.php, htaccess.dist     front controller and .htaccess template
├── build/, themes/, js/, css/   public assets
├── uploads/                     uploaded images (created by the installer, never part of a package)
└── core/                        application code: denied to browsers by .htaccess
    ├── .env                     configuration and secrets (written by the installer, never part of a package)
    ├── storage/                 CV PDFs, cache, logs (never part of a package)
    └── themes/                  themes (additional themes are installed here too)
```

If your hosting lets you keep files outside the web root, you can move `core/` there and create
`public_html/core-path.php` containing `<?php return '/home/account/resume-core';`.

## First installation

1. **Database** — create a MySQL database (e.g. `account_resume`) and a user, then give the user *All privileges* on
   the database. Write down the three names and the password.
2. **PHP version** — choose PHP 8.4 or 8.5 for the site and enable the extensions listed above (most are on by
   default; `intl` and `zip` are the ones usually missing).
3. **Upload** — with the file manager of the panel (or FTP) upload the zip to the web root (`public_html`,
   `httpdocs`... depending on the hosting) and extract it there. Remove any old site files first (keep a copy): only
   the `.htaccess` lines added by the panel may stay.
4. **Install** — open `https://your-domain/install`. If the address does not work yet, open
   `https://your-domain/index.php/install` (the `.htaccess` rules are written at the end of the installation).
   Fill in the form:
   - site address, your name, main language and other languages;
   - database: server `localhost`, port `3306`, the database name and user created in step 1;
   - administrator email and password, and the path of the admin area (anything but `admin` is a good idea);
   - HTTPS and "without www" redirects (turn on HTTPS only once the certificate works).
5. Done. The installer creates the tables, the administrator, `core/.env` (permissions `0600`), the `uploads/`
   folder and the managed block in `public_html/.htaccess`. From now on `/install` answers 404.

### Where to find it in the control panel

Menu names change from one version to the next; these are the usual ones.

| | cPanel | Plesk | DirectAdmin |
|---|---|---|---|
| Database and user | *MySQL Databases* | *Databases* | *MySQL Management* |
| PHP version and extensions | *Select PHP Version* or *MultiPHP Manager* | *PHP* (in the domain settings) | *Select PHP version* |
| Files | *File Manager* | *File Manager* | *File Manager* |
| Cron job | *Cron Jobs* | *Scheduled Tasks* | *Cron Jobs* |

Then, in the admin area (`https://your-domain/<admin path>`):

1. user menu → *Profile*: turn on **two-factor authentication** and keep the recovery codes;
2. *Website* → *Site languages*: check the default language and the active ones;
3. *Résumé* → *Profile*: name, photo, headlines, contacts; then the CV sections (experience, projects, education...);
4. *Content* → *CV PDFs*: upload the CV of each language;
5. *Website* → *Settings*: SEO texts, sharing image, site icon, privacy options;
6. *Website* → *CV layout*: order of the sections and which ones appear in the menu;
7. open the site (*Go to site* in the top bar) and check it in every language.

[CONFIGURATION.md](CONFIGURATION.md) describes every page of the admin panel.

### What the installer asks

| Field | Notes |
|---|---|
| Site address | the public address, e.g. `https://www.example.com` (it becomes `APP_URL`) |
| Your name | the owner of the CV: profile name, site name and sender of the emails |
| Main language, other languages | the site languages; more can be added later from the admin |
| Sample CV | loads the "John Doe" demo content, useful to see how everything works |
| Database | server (almost always `localhost` on shared hosting), port (`3306`), database name, user, password |
| Administrator | email and password (at least 10 characters) |
| Admin path | the address of the admin area (`/admin` by default: a less obvious one keeps bots away) |
| HTTPS, without www | permanent redirects; turn on HTTPS only when the certificate already works |

The installer speaks Italian or English, following the browser language; the admin interface uses the same language
(it can be changed later with `RESUME_ADMIN_LOCALE`, see [CONFIGURATION.md](CONFIGURATION.md#server-settings-coreenv)).

### Safety checks done by the installer

- Before writing anything it requests `https://your-domain/core/VERSION`: if the file can be downloaded, the server
  is not applying `.htaccess` files and the installation stops (application files and `.env` would be exposed).
- An existing Resume App database is **kept**: content stays as it is, and the administrator with the given email
  gets the new password (two-factor authentication must be turned on again). This is the way to recover a
  lost `.env` or a lost password.

## Updating

Every release is a **full package**: there are no patches to apply in a specific order.

### From the admin (version 2.0.3 and later)

Admin → *System* → **Updates**:

- the site checks the GitHub releases of `RESUME_UPDATE_REPOSITORY` (default `Razzo1987/Resume-Application`):
  with the cron job every 30 minutes (conditional requests, which do not count against the GitHub limit), without it
  at most every 12 hours when the dashboard is opened; it shows "Version x.y.z is available" with its release notes
  and emails the administrators; *Check now* checks immediately;
- **Download and install** fetches the package straight from GitHub to the server, in 8 MB chunks, and verifies the
  SHA-256 published with the release; **Upload a package** sends a zip from your computer in 1 MB chunks, so the
  hosting upload limit does not matter; a zip copied into `core/storage/app/private/updates/packages` with the File
  Manager is listed too;
- before starting, the package is checked: it is a Resume App package, not older than the installed version, the
  server has the required PHP version and extensions, installed themes declare themselves compatible, there is
  enough disk space and the folders are writable;
- **Update now** exports the content (safety copy), turns maintenance on and opens a progress page: the files are
  extracted in short steps by a small script that runs before Laravel (the application is not started while its
  files are replaced; visitors get a static "Site being updated" page). If a step fails, *Retry* resumes from there;
- back on the *System* page the update is completed automatically (migrations, caches, `.htaccess` block, version)
  and maintenance is turned off if it was off before.

While the repository is private, a GitHub token is needed: a **fine-grained personal access token** with access to
that repository only and the *Contents: Read-only* permission. Enter it with *GitHub token* on the same page (stored
encrypted with the `APP_KEY` in `core/storage`, never in content exports) or set `RESUME_UPDATE_TOKEN` in
`core/.env`. Public repositories need no token. If the server cannot reach GitHub, upload the package instead.

### Scheduled jobs (cron) and automatic updates

One cron job runs everything that has to happen by itself: the update check, automatic updates, the GeoLite
databases of the statistics, the daily statistics summaries and the clean-up of temporary files.

1. Admin → *System* → **Scheduled jobs**: the page shows the exact line, with the PHP path of your server, e.g.
   `*/5 * * * * /usr/local/bin/php /home/user/public_html/core/artisan schedule:run >/dev/null 2>&1`.
2. In the control panel open the cron jobs (see [the table above](#where-to-find-it-in-the-control-panel)), add a
   job every five minutes and paste the command (if the panel asks for a user, use the one that owns the site).
3. Within a few minutes the page shows "active" with the PHP version used by the cron job: it must be the same as the
   site's. On many hostings the command line PHP is a different version: if so, change the path in the command
   (the hosting documentation lists the path of each version).

Without the cron job the site works anyway: the light jobs (update check, statistics summaries) start from the admin
at most once an hour, and downloads are done from the admin pages.

**Automatic updates** (*System* → *Updates* → *Change*), with the cron job active:

- **Off**: only the notice on the dashboard;
- **Download and notify** (default): the package is downloaded to the server and checked; you press *Check and
  install*;
- **Install fix releases automatically**: fix releases (same x.y, e.g. 2.1.0 → 2.1.1) install themselves, with the same
  checks and content export as from the admin (the PHP version and extensions of the web server are checked too);
  releases with new features (2.2.0) are only downloaded, because they can change content or themes.

The result is shown on the dashboard and emailed to the administrators. If an automatic update stops halfway, the
cron job retries it; the email also contains a link to continue it from the browser. From the command line:
`php core/artisan resume:update --check` and `php core/artisan resume:update --finish`.

### By hand (FTP or File Manager)

1. Admin → *System* → *Start maintenance*, then *Tools* → *Export the content* (or a full backup from the control panel).
2. Extract the new zip into `public_html`, overwriting the files.
   `core/.env`, `uploads/`, `core/storage/` (CV PDFs) and the installed themes are not part of the package and are never touched.
3. Admin → *System*: the page shows "New version uploaded" → press **Complete the update**
   (database migrations, cache clean-up, `.htaccess` block rewritten, new version recorded).
4. *Tools* → *Files no longer used*: lists the files of previous versions that are not in the new package
   (compared with `core/manifest.json`) and deletes them, together with the folders left empty.
5. *End maintenance*.

While the site is in maintenance, visitors get a "Site being updated" page (HTTP 503, `Retry-After`), search
engines are told to come back later, and the admin area keeps working.

## Moving the content

*System* → *Tools* → *Export the content* creates a zip with the content (database tables, uploaded images, CV PDFs;
no users, passwords or `.env`). *Import content* on another installation (same or newer version) replaces all its
content with the zip; users and passwords stay as they are, and a copy of the previous content is saved in
`core/storage/app/private/backups`.

### From version 1

The content of Resume Application 1.x is imported with a command, usually on a development copy; the result is then
moved to the server with *Export* / *Import content*:

```bash
mysql -u root -e "CREATE DATABASE resume_v1" && mysql -u root resume_v1 < dump-v1.sql   # or LEGACY_DB_* in .env
php artisan resume:import-v1 --files=/path/to/v1/public_html --volunteering=9,13 \
    --cv="it-IT=/path/to/v1/public_html/resume/My CV.pdf" --fresh
```

`--volunteering` lists the v1 ids of the volunteering experiences; `--cv` sets the current CV of a language (the
other languages offer that one). The command prints what it imported and what to check by hand (for example full
addresses used as locations, or dates that differ between languages). Old addresses of certificates
(`/attachment/…`) and CVs (`/resume/…`) are redirected to the new ones.

## Themes

The package contains the open `base` theme. Additional themes (your own or third-party ones) are installed from their
zip in the admin:
**Themes** → *Install or update a theme* → upload → *Activate*. Theme files go to `core/themes/{name}` and the
compiled assets to `themes/{name}` in the web root; updating the application never touches them. Installing a newer
zip of the same theme updates it in place. A theme that is not in use can be removed from the same page.

## The `.htaccess` file

Resume App writes only its own block, between `# BEGIN resume-app` and `# END resume-app`; everything outside it
(for example the PHP version handler added by the control panel) is left untouched. The block:

- denies `core/`, `core-path.php` and hidden files;
- optionally redirects to HTTPS and to the domain without `www`;
- removes trailing slashes and routes everything else to `index.php`;
- sets one-year immutable caching for compiled theme assets and uploaded images, and compression for text files.

The block can be rewritten from *System* → *Tools* → *.htaccess rules* (for example to turn on HTTPS later).
The template is `htaccess.dist`.

### Without `.htaccess` (nginx)

A server that runs nginx alone does not read `.htaccess` files, and the installer stops because `core/` can be
downloaded. The same rules have to go into the site configuration; an example to adapt (socket path and folders
depend on the server):

```nginx
root /home/user/public_html;
index index.php;

location ~ ^/(core|core-path\.php)(/|$) { deny all; return 404; }
location ~ /\.(?!well-known) { deny all; return 404; }
location / { try_files $uri /index.php?$query_string; }
location ~ \.php$ { include fastcgi_params; fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name; fastcgi_pass unix:/run/php/php8.4-fpm.sock; }
```

The caching and compression headers of the block are not essential. Panels that put nginx in front of Apache (as
Plesk does by default) still read `.htaccess` files and need nothing of this.

## Troubleshooting

| Problem | What to check |
|---|---|
| `/install` gives 404 on a fresh upload | use `/index.php/install`; check that `core/.env` does not exist |
| "The core folder can be reached from the browser" | the server ignores `.htaccess`: ask the provider to enable `AllowOverride All`, or move `core/` outside the web root (`core-path.php`) |
| Error 500 after an update | *System* → *Complete the update*; if the admin does not open, check `core/storage/logs/laravel.log` |
| Pages not changing after an edit | the page cache is cleared automatically; *System* → *Tools* → *Clear caches* forces it |
| Lost admin password | run the installer again: rename `core/.env` to `core/.env.old`, open `/install` with the same database and email |

## Removing the previous version (v1)

The old application can be removed once the new site is online and checked. Keep a copy of the old database
dump and of the uploaded files until the data import has been verified.
