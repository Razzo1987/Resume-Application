# Configuring Resume App

How to set up the site after the installation ([INSTALL.md](INSTALL.md)). Almost everything is done from the admin
panel; a few server-level options live in `core/.env`.

- [Where things are configured](#where-things-are-configured)
- [First steps](#first-steps)
- [The admin panel](#the-admin-panel)
  - [Résumé](#résumé)
  - [Content](#content)
  - [Website](#website)
- [Languages and translations](#languages-and-translations)
- [Settings](#settings)
- [Key figures](#key-figures)
- [Icons](#icons)
- [Server settings (`core/.env`)](#server-settings-coreenv)
- [Privacy and security](#privacy-and-security)
- [Backups](#backups)

## Where things are configured

| What | Where |
|---|---|
| CV content, sections, languages, SEO, privacy options, themes | admin panel (stored in the database, included in content exports) |
| Admin address and language, update channel, database, mail | `core/.env` (written by the installer) |
| Site texts and words used in the URLs of each language | language packs (`core/lang/{code}`), with overrides from the admin |
| Look and feel | the active theme (`base` included, more can be installed from the admin) |

Every change made in the admin is online immediately: the page cache is cleared automatically.

## First steps

1. user menu → *Profile*: turn on **two-factor authentication** and save the recovery codes (and choose the admin
   language);
2. *Website* → *System* → *Scheduled jobs*: add the **cron job** in the hosting control panel (one line, shown on the page), so updates
   and statistics work by themselves;
3. *Website* → *Site languages*: default language and active languages;
4. *Résumé* → *Profile*, then the CV sections;
5. *Content* → *CV PDFs*: one CV for each language;
6. *Website* → *Settings*: SEO title and description, sharing image, site icon, privacy notice;
7. *Website* → *CV layout*: section order and menu;
8. *Statistics* (optional): turn them on and set the MaxMind account for countries and cities;
9. *Go to site* (top bar) to check the result in every language.

If you installed the sample CV, edit or delete its content: everything belongs to the fictional "John Doe".

## The admin panel

Forms have **one tab for each active language**: the default language comes first and is the only one with required
fields. Texts left empty in a language fall back to the default language on the site. Dates, links, images and
options are the same for every language and are entered once.

The address of the admin area is the one chosen during the installation (`RESUME_ADMIN_PATH`). The top bar has
*Go to site*, which opens the public site in the admin language when the site has it.

The dashboard shows alerts (update available or downloaded, automatic update done or failed, update to complete,
maintenance on, `core/` folder reachable from the browser) and then one group of cards per type, each linking to
where you act:

- **System**: version and available update, cron job, automatic updates, date of the last content export;
- **Languages**: site languages and, for each one, the texts still to translate;
- **Content**: work experience and volunteering, projects with a page of their own, published, draft and scheduled
  articles, active CV sections that stay hidden because they are empty;
- **CV**: the PDF of each language with its upload date (and what visitors get when it is missing);
- **Statistics**: views and CV downloads of the last 7 days with the change on the week before, most viewed page,
  main source and country (or how to turn statistics on).

Each administrator chooses the **admin language** in the profile (user menu → *Profile*); without a choice, and on
the sign-in page, `RESUME_ADMIN_LOCALE` applies.

### Statistics

*Statistics* (right below the dashboard) is off until you turn it on with *Settings* on that page.

- **What is recorded**, for every page view and CV download: date and time, page, language, referring site, campaign
  parameters (`utm_source`, `utm_medium`, `utm_campaign`, `utm_term`, `utm_content`), the platform of the click when
  the address has a click ID (`fbclid` is stored as "facebook", the ID itself never), device, operating system,
  browser, and from the IP address country, region, city and network (provider or company).
- **What is not**: no cookies and nothing read from the device, so no cookie banner is needed. The IP address is
  shortened straight away (`93.44.12.x`, IPv6 to the first 48 bits) and never stored in full. Bots, scripts (curl,
  libraries), browser previews (prefetch) and the administrators' visits do not count.
- **Your own visits** do not count in two ways: from the browser signed in to the admin (the admin session cookie is
  only read, visitors never get one), whatever the address; and from the public address you use the admin from, for
  14 days after your last access (change it in *Settings*; *Excluded IPs* lists and removes them), which also covers
  your phone on the same network. With a shared address (office, mobile network) other people would be excluded too:
  that is why the exclusion expires.
- **Behind a proxy** (CDN, tunnel) the site must trust it to read the visitor's address: set `RESUME_TRUSTED_PROXIES`
  (see [Server settings](#server-settings-coreenv)), otherwise every visitor gets the proxy's address. Some proxies do
  not pass the visitor's address at all: GitHub Codespaces port forwarding gives everybody the same private address.
  Private and local addresses (10.x, 192.168.x, 127.0.0.1...) are never excluded as admin IPs, so visits are still
  counted there, without country, city and network; the *Statistics* page says so when it happens.
- **How long**: single visits are deleted after 12 months (configurable); the daily totals per page stay, so the
  charts keep working for older periods.
- **Location**: the free MaxMind GeoLite2 City and ASN databases, installed on the server (the IP address is not sent
  to anyone). Create a free account at [maxmind.com](https://www.maxmind.com/en/geolite2/signup), generate a license
  key, then *MaxMind account* on the *Statistics* page (or `RESUME_GEOIP_ACCOUNT_ID` and
  `RESUME_GEOIP_LICENSE_KEY` in `core/.env`). *Update the databases* downloads them; with the cron job they are
  checked every day and updated by themselves, as the GeoLite terms ask. Without the databases everything works
  except country, city, network and the map.
- **The page**: totals, visits over time, most viewed pages, referring sites, campaigns, languages, a world map with
  countries and cities, networks, devices, systems, browsers, shortened IP addresses, and the list of the latest
  visits.
- **Filters**: period and page (for example a single blog article), and under *More filters* referring site, UTM
  source, medium and campaign, platform click, country, city, network, device, system, browser, language and
  shortened IP address. Filters add up (for example: the pages seen from one campaign, or everything one address
  looked at), and a click on an entry of a ranking or on an IP address in the latest visits sets it. The filters are
  in the page address, so a filtered view can be bookmarked. With filters other than period and page the data comes
  from the single visits, so it goes back as far as they are kept (12 months by default).
- **Deleting visits**: select them in the latest visits (for example after filtering by your IP address) and
  *Delete*; the totals of their days are recalculated. Useful for your own visits made while not signed in to the
  admin.
- Turning statistics on also turns on the **Privacy page** (see [Settings](#settings)), with a section describing them.
  Statistics are never part of content exports.

### Résumé

| Page | What it is for |
|---|---|
| **Profile** | Full name, photo (with alternative text), headlines (one per line: they rotate under the name), short bio, location, email, phone, birth date, career start (used by the "years of experience" figure). |
| **Key figures** | The numbers shown at the top of the CV, see [Key figures](#key-figures). |
| **Experience** | Work experience: role, organisation, place, dates (no end date = current), summary, details, skills. An experience gets its own page when it has details or related projects. *Move to volunteering* moves it to the other list. |
| **Volunteering** | Same fields, shown in their own section. |
| **Projects** | Title, role, sector, client, related experience, dates, summary, details, image, skills, "featured" flag, SEO title and description. A project gets its own page when it has details or an image. |
| **Education** | Degree, field of study, institution, dates, grade (with scale and cum laude), thesis title, summary, details (e.g. the study plan, shown as an expandable block and not printed). |
| **Certifications** | Title, type (certification, course, exam, badge...), issuer, issue and expiry dates, credential ID, verification link, badge image, PDF of the certificate. |
| **Languages spoken** | Language with CEFR levels (A1-C2) for oral, written and comprehension, or native; optional certification and note. |
| **Publications and talks** | Articles, papers, theses, books, talks, webinars, presentations, podcasts: venue, date, co-authors, link or PDF. |
| **Organisations** | Companies, clients, universities, certifiers, associations: name, type, website, logo. Used by experience, projects, education and certifications (they can also be created on the fly from those forms). |
| **Interests** | Name, short description and an [icon](#icons). |
| **Skills** and **Skill categories** | Skills grouped by category (for example "Expertise", "Tools", "Soft skills"), linked to experience and projects; "featured" skills stand out. |

Every list can be reordered by dragging the rows (where order matters) and every item has a *Visible on the site*
switch, so things can be prepared and published later. Items with a public page have *View on site*.

### Content

| Page | What it is for |
|---|---|
| **Blog articles** | Title, slug, excerpt, body, cover image (1200 x 630 works best: it is also the sharing image), status (draft or published) and publication date. An article appears only in the languages it has a title in; a future date schedules it. |
| **CV PDFs** | One PDF for each language, with history: a new upload becomes the current version, an older one can be made current again. The file is stored outside the web root and downloaded at `/{language}/cv` with the name set in *Settings*. |
| **Translations** | For each language, the content written in another language and still empty in this one (CV items, section titles, settings texts), with a link to edit each item. |

### Website

| Page | What it is for |
|---|---|
| **CV layout** | The sections of the CV page: on or off, shown in the menu or not, custom title for each language, order (drag the rows). The *Latest articles* section also decides the "Blog" menu item, which always opens the blog page (even when the section is hidden on the CV). Sections without content are never shown. |
| **Site languages** | Default and active languages, the words used in the URLs (e.g. `projects` / `progetti`) and custom texts that replace single strings of the language pack. See [Languages and translations](#languages-and-translations). |
| **Social profiles** | LinkedIn, GitHub, GitLab, X, Bluesky, Mastodon, Facebook, Instagram, YouTube, Medium, SlideShare, Stack Overflow or any website, with order and visibility. |
| **Redirects** | Old addresses that must lead to new ones (301 or 302), for example after moving from another site. Changing a slug or a URL word creates the redirect by itself. |
| **Settings** | See [Settings](#settings). |
| **Themes** | Installed themes with their version: *Activate*, *Install or update a theme* (zip), remove the ones not in use. |
| **System** | Version, updates (also automatic), scheduled jobs (cron), maintenance and tools: clear caches, check the protection of `core/`, `.htaccess` rules, delete files left by older versions, export and import the content. Updates are described in [INSTALL.md](INSTALL.md#updating). |

## Languages and translations

- **Language codes** are full locale codes (`it-IT`, `en-GB`) and are also the first part of every address:
  `/it-IT`, `/en-GB/projects/booking-portal`. `/` sends visitors to the default language.
- **Language packs** (`core/lang/{code}/site.php` and `routes.php`) contain the site texts and the URL words. The
  package includes it-IT, en-GB, en-US, de-DE, fr-FR, es-ES, pt-PT, pt-BR and nl-NL (the last six are drafts to be
  reviewed by native speakers). A language without a pack uses the English texts.
- **Adding a language**: *Site languages* → *Create*, with its code; then fill in its tab in the content. Until a
  text is translated, the site shows the default language.
- **URL words** can be changed per language in *Site languages*; the old addresses keep working with a permanent
  redirect. **Custom texts** replace single strings of the pack (the key is the one in `site.php`, e.g.
  `actions.download_cv`).
- **Slugs** are generated from the title of each language and can be edited; changing one creates a redirect.
- The *Translations* page and the "Missing translations" column of the lists show what is left to translate. In
  *Profile* and *Settings* each language tab shows how many texts are still empty (their names on hover), and the
  *Presentation* section of the profile lists them.

## Settings

| Section | Options |
|---|---|
| **Appearance** | Active theme (changed from *Themes*), "Print" button, "Last updated on" date in the footer. |
| **Time zone** | The time zone of the site, proposed from the country of the default language (e.g. `Europe/Rome` for it-IT). It sets when a day starts for the statistics and the dates of articles and "Last updated on", and the dates and times shown in the admin. |
| **CV download** | Name of the downloaded file, with the placeholders `{name}` (full name), `{lang}` (IT, EN...) and `{locale}` (it-IT, en-GB...), e.g. `CV {name} - {lang}.pdf`; whether a language without its own CV offers the one of the default language. |
| **Contact details on the page** | Show email, phone and birth date on the CV. Email and phone are encoded in the HTML and revealed by the page script, which keeps most spam bots away. |
| **Structured data (JSON-LD)** | Include email, phone and birth date in the data read by search engines. Off by default: turn them on only if you want them to appear in search results. |
| **Privacy notice** | The Privacy page (`/{language}/privacy`) and its link in the footer, off by default and always on with visit statistics. For each language write your own text, or start from the template (*Use the template*) and edit it; a language without a text of its own shows the template. `{name}` becomes the profile name and `{email}` your email, encoded as in the rest of the site. With statistics on, a section describing them (data, shortened IP, legitimate interest, months of retention, GeoLite) is added by itself. The template is not legal advice: check it for your case. |
| **SEO** | Sharing image (Open Graph, 1200 x 630), site icon (square PNG, at least 512 px), and for each language the page title, the description and the footer text (for example copyright and data processing consent). |

## Key figures

Each key figure is a number with a label, an optional unit and an [icon](#icons). The value can be:

| Source | Value |
|---|---|
| Manual | the number you type, e.g. 460 with the label "data quality rules migrated" |
| Years of experience | from the career start in *Profile*, or the start of the first work experience |
| Projects, Certifications, Publications, Articles | counted from the visible items |

*Display* shows the number as it is or followed by "+" (for example "460+" or "100.000+"). Automatic figures get
their label from the language pack when you leave it empty. Numbers are formatted for each language
(100.000 / 100,000).

## Icons

Interests and key figures use **semantic icon names**: the theme decides how each name is drawn (the `base` theme uses
Font Awesome Free, other themes may use other icon sets). The field lists the names available in the active
theme; these are the ones of `base` (other themes can add their own):

| Group | Names |
|---|---|
| Work, sections | `briefcase`, `building`, `project`, `graduation`, `certificate`, `language`, `publication`, `talk`, `volunteering`, `interests`, `award`, `tools`, `users`, `code`, `chart`, `blog`, `lightbulb`, `rocket`, `trophy`, `database`, `shield` |
| Work, skills and sectors | `brain`, `microchip`, `cloud`, `server`, `network`, `security`, `checklist`, `search`, `handshake`, `target`, `flag`, `medal`, `percent`, `pie-chart`, `money`, `teaching`, `science`, `heart`, `community`, `energy`, `bank`, `insurance`, `telecom`, `ai` |
| Sport and outdoors | `running`, `cycling`, `hiking`, `mountain`, `swimming`, `skiing`, `football`, `basketball`, `fitness`, `wellness`, `sailing` |
| Free time and culture | `camera`, `music`, `travel`, `chess`, `reading`, `gaming`, `dice`, `puzzle`, `cinema`, `theatre`, `guitar`, `headphones`, `podcast`, `art`, `writing`, `cooking`, `coffee`, `wine`, `gardening`, `pets`, `world`, `car`, `motorbike` |

Only the icons listed in the theme's `icons.php` are included in its SVG sprite, so pages stay light. A new icon is
added to the theme (`themes/{name}/icons.php`) and the theme is rebuilt.

## Server settings (`core/.env`)

The installer writes `core/.env` (permissions `0600`). Edit it with the File Manager only when needed; changes apply
to the next request (if they do not, *System* → *Tools* → *Clear caches*).

| Variable | Default | Meaning |
|---|---|---|
| `APP_URL` | from the installer | public address of the site, used for absolute links, sitemap and canonical URLs |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` | never turn `APP_DEBUG` on in production: errors would show paths and settings |
| `APP_KEY` | generated | encryption key (sessions, two-factor secrets, GitHub token); changing it logs everybody out |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | from the installer | languages used before the database is ready; the site languages are managed from the admin |
| `RESUME_ADMIN_PATH` | `admin` | address of the admin area (`/admin`) |
| `RESUME_ADMIN_LOCALE` | `it` or `en` | default language of the admin interface (each administrator can choose another one in the profile) |
| `RESUME_THEME` | `base` | theme used when none is active in the admin (for example after a failed theme installation) |
| `RESUME_PAGE_CACHE` | `true` | HTML cache of the public pages; turn it off only to debug |
| `RESUME_TRUSTED_PROXIES` | empty | only when the site is behind a proxy that changes the request address (CDN or tunnel such as Cloudflare, GitHub Codespaces): `*` for any proxy, or a comma-separated list of IP addresses or networks. Address, protocol and visitor IP are then read from the `X-Forwarded-*` headers. Leave it empty otherwise: with `*` and no proxy in front, anybody could fake those headers |
| `RESUME_UPDATE_REPOSITORY` | `Razzo1987/Resume-Application` | GitHub repository whose releases are offered as updates |
| `RESUME_UPDATE_TOKEN` | empty | GitHub token for a private repository (it can also be entered from *System*) |
| `RESUME_UPDATE_API` | `https://api.github.com` | GitHub API address (GitHub Enterprise or tests) |
| `RESUME_GEOIP_ACCOUNT_ID`, `RESUME_GEOIP_LICENSE_KEY` | empty | MaxMind account for the GeoLite databases of the statistics (they can also be entered from *Statistics*) |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | from the installer | database connection |
| `MAIL_MAILER`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `sendmail`, `noreply@your-domain`, your name | emails sent by the admin (password reset). If the hosting needs SMTP, set `MAIL_MAILER=smtp` with `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` |
| `LOG_CHANNEL`, `LOG_LEVEL` | `daily`, `warning` | error logs in `core/storage/logs` |

## Privacy and security

- The public site sets **no cookies**, loads **no external resources** and sends a strict Content Security Policy;
  only the admin area uses a session cookie.
- The admin area is not indexed by search engines (`noindex`) and supports two-factor authentication with an app
  (Google Authenticator, 1Password...) and recovery codes.
- `core/` is denied to browsers by `.htaccess`; the dashboard warns you if the server does not apply it.
- Uploaded images are resized and converted to WebP; CV PDFs are stored outside the web root.
- Visit statistics (off by default) use no cookies and store the IP address only shortened; see [Statistics](#statistics).
- Content exports contain no users, passwords, `.env`, GitHub token, MaxMind account or statistics.

## Backups

- *System* → *Tools* → **Export the content**: a zip with the database content, the uploaded images and the CV PDFs.
  Keep one after every important change; an export is also created automatically before every update and import.
- **Import the content** replaces all the content with an export (from the same or an older version); users and
  passwords stay as they are.
- For a full backup (files and database) use the backup tool of the hosting control panel.
