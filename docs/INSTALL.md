# Installing and updating Resume App

This guide covers a standard shared hosting account with **cPanel**; any Apache + PHP + MySQL server works the same way.

## Requirements

| | |
|---|---|
| PHP | 8.4 or 8.5 with `ctype`, `dom`, `fileinfo`, `filter`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `session`, `tokenizer`, `zip` |
| Database | MySQL 8.0+ or MariaDB 10.11+ |
| Web server | Apache with `mod_rewrite` and `.htaccess` files enabled (`AllowOverride All`) |
| Recommended | `mod_headers`, `mod_deflate`, an SSL certificate (AutoSSL / Let's Encrypt) |

The installer checks all of them before doing anything.

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
    └── themes/                  themes (PRO themes are installed here too)
```

If your hosting lets you keep files outside the web root, you can move `core/` there and create
`public_html/core-path.php` containing `<?php return '/home/account/resume-core';`.

## First installation

1. **Database** — cPanel → *MySQL Databases*: create a database (e.g. `account_resume`) and a user, then add the
   user to the database with *All privileges*. Write down the three names and the password.
2. **PHP version** — cPanel → *Select PHP Version* (or *MultiPHP Manager*): choose PHP 8.4 or 8.5 and enable the
   extensions listed above (most are on by default; `intl` and `zip` are the ones usually missing).
3. **Upload** — cPanel → *File Manager* → `public_html`: upload the zip and *Extract* it there.
   Remove any old site files first (keep a copy): only the `.htaccess` lines added by cPanel may stay.
4. **Install** — open `https://your-domain/install`. If the address does not work yet, open
   `https://your-domain/index.php/install` (the `.htaccess` rules are written at the end of the installation).
   Fill in the form:
   - site address, your name, main language and other languages;
   - database: server `localhost`, port `3306`, the database name and user created in step 1;
   - administrator email and password, and the path of the admin area (anything but `admin` is a good idea);
   - HTTPS and "without www" redirects (turn on HTTPS only once the certificate works).
5. Done. The installer creates the tables, the administrator, `core/.env` (permissions `0600`), the `uploads/`
   folder and the managed block in `public_html/.htaccess`. From now on `/install` answers 404.

Then, in the admin area:

- user menu → *Profile*: turn on two-factor authentication;
- *Settings* → choose the theme, the CV file name and the other options;
- fill in *Profile* and the CV sections, upload the CV PDF of each language.

### Safety checks done by the installer

- Before writing anything it requests `https://your-domain/core/VERSION`: if the file can be downloaded, the server
  is not applying `.htaccess` files and the installation stops (application files and `.env` would be exposed).
- An existing Resume App database is **kept**: content stays as it is, and the administrator with the given email
  gets the new password (two-factor authentication must be turned on again). This is the way to recover a
  lost `.env` or a lost password.

## Updating

Every release is a **full package**: there are no patches to apply in a specific order.

1. Admin → *System* → *Start maintenance*, then *Tools* → *Export the content* (or a full backup from cPanel).
2. Extract the new zip into `public_html`, overwriting the files.
   `core/.env`, `uploads/`, `core/storage/` (CV PDFs) and PRO themes are not part of the package and are never touched.
3. Admin → *System*: the page shows "New version uploaded" → press **Complete the update**
   (database migrations, cache clean-up, `.htaccess` block rewritten, new version recorded).
4. *Tools* → *Files no longer used*: lists the files of previous versions that are not in the new package
   (compared with `core/manifest.json`) and deletes them, together with the folders left empty.
5. *End maintenance*.

While the site is in maintenance, visitors get a "Site being updated" page (HTTP 503, `Retry-After`), search
engines are told to come back later, and the admin area keeps working.

## Themes

The package contains the open `base` theme. Additional themes are installed from their zip in the admin:
**Themes** → *Install or update a theme* → upload → *Activate*. Theme files go to `core/themes/{name}` and the
compiled assets to `themes/{name}` in the web root; updating the application never touches them. Installing a newer
zip of the same theme updates it in place. A theme that is not in use can be removed from the same page.

## The `.htaccess` file

Resume App writes only its own block, between `# BEGIN resume-app` and `# END resume-app`; everything outside it
(for example the PHP version handler added by cPanel) is left untouched. The block:

- denies `core/`, `core-path.php` and hidden files;
- optionally redirects to HTTPS and to the domain without `www`;
- removes trailing slashes and routes everything else to `index.php`;
- sets one-year immutable caching for compiled theme assets and uploaded images, and compression for text files.

The block can be rewritten from *System* → *Tools* → *.htaccess rules* (for example to turn on HTTPS later).
The template is `htaccess.dist`.

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
