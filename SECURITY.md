# Security policy

## Supported versions

Only the latest release receives security fixes. Fix releases (x.y.Z) can be installed automatically by the
sites that enable it in *System → Updates*, so please keep your installation up to date.

## Reporting a vulnerability

Please **do not open a public issue**. Use GitHub's private reporting instead:
[Report a vulnerability](https://github.com/Razzo1987/Resume-Application/security/advisories/new).

Include the version, the steps to reproduce and the impact you expect. You will get an answer within a week; once a
fix is released, the advisory is published with credit to the reporter (unless you prefer otherwise).

## Threat model

What the application protects, and what it trusts by design:

- **Public site**: no session, no cookies, no forms, strict Content Security Policy. Anything that lets a visitor
  run code, read private data, set cookies or reach the admin is a vulnerability.
- **Administrators are trusted with the server.** Themes contain Blade views and PHP files, and update packages
  replace the application code: installing a theme or a package from the admin is, by design, running code on the
  server. Reports that require installing a malicious theme or package are therefore out of scope. Actions that an
  administrator does not expect to run code (restoring a content export, uploading an image or a PDF) must never do
  so: those are in scope.
- **Update channel**: packages come from the GitHub releases of this repository and are checked against the SHA-256
  and the Ed25519 signature published with them (from 2.4.0: the public keys are in
  `app/Support/Update/Signature.php`, the private key is used only by the release workflow for tags signed with the
  maintainer's GPG key). Packages uploaded by hand are installed only by an administrator, with a note that their
  signature was not checked; automatic updates install only signed packages. Anything that makes a site install a package that was not published by this repository is in
  scope.
- **Installer**: available only until the `.env` file exists.
- **Release layout**: the application code lives in `core/` inside the web root and is protected by `.htaccess`
  (Apache, LiteSpeed). On servers that ignore `.htaccess` the dashboard warns that `core/` is exposed; that setup is
  not supported.

Out of scope: denial of service by volume, missing hardening on unsupported servers, findings that need a
compromised administrator account, the hosting account or the GitHub account of the maintainer.
