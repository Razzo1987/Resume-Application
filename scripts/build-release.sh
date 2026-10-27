#!/usr/bin/env bash
# Builds the full release package: releases/resume-app-<version>.zip
#
# Zip layout (extract EVERYTHING into the web root, e.g. public_html/):
#   index.php, htaccess.dist, build/, themes/…   ← content of public/
#   core/                                        ← rest of the app (protected by core/.htaccess)
#   core/VERSION, core/manifest.json
#
# The package never contains data: .env, storage/ (empty structure only), public/uploads, PRO themes.
# Usage: scripts/build-release.sh [output-folder]   (from the repo root, with a clean working tree)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../releases}"
mkdir -p "$OUT" && OUT="$(cd "$OUT" && pwd)"   # absolute path: the zip is created from another folder
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
NAME="resume-app-${VERSION}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "== Exporting the versioned files only"
mkdir -p "$WORK/src"
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$WORK/src"

echo "== Production dependencies and assets"
( cd "$WORK/src"
  composer install --no-dev --prefer-dist --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
  # If Composer had to clone the packages from git (dist not downloadable), no .git folders in the package
  find vendor -type d -name .git -prune -exec rm -rf {} +
  # vendor cleanup: the packages' tests, docs and examples are not needed in production
  find vendor -mindepth 3 -maxdepth 3 -type d \( -iname tests -o -iname test -o -iname test_files -o -iname legacy_tests -o -iname docs -o -iname doc -o -iname examples -o -name .github \) -prune -exec rm -rf {} +
  # Package maintenance tools (e.g. Laravel's splitsh-lite binary, 7 MB)
  rm -rf vendor/laravel/framework/bin
  find vendor -type f \( -iname '*.md' -o -name 'phpunit.xml*' -o -name 'phpstan.neon*' -o -name '.editorconfig' -o -name '.gitattributes' \) -delete
  npm ci --no-audit --no-fund
  npm run build
  rm -rf node_modules tests docs .github .devcontainer .editorconfig phpunit.xml phpstan.neon* pint.json \
         package.json package-lock.json vite.config.* playwright.config.* scripts
)

echo "== Layout: public/ → root, the rest → core/"
PKG="$WORK/$NAME"
mkdir -p "$PKG/core"
shopt -s dotglob
mv "$WORK/src/public/"* "$PKG/"
rmdir "$WORK/src/public"
mv "$WORK/src/"* "$PKG/core/"
shopt -u dotglob
[ -f "$PKG/.htaccess" ] && rm "$PKG/.htaccess"        # .htaccess is never overwritten
cp "$PKG/core/stubs/htaccess.dist" "$PKG/htaccess.dist"
cp "$PKG/core/stubs/core.htaccess" "$PKG/core/.htaccess"
rm -rf "$PKG/uploads" "$PKG/core/.env" "$PKG/core/.env.example.local"
# storage: structure only (the folders with their .gitignore), no data
find "$PKG/core/storage" -type f ! -name .gitignore -delete

echo "== Manifest (to remove orphan files during updates)"
echo "$VERSION" > "$PKG/core/VERSION"
( cd "$PKG" && find . -type f ! -path './core/manifest.json' -print0 | sort -z \
  | xargs -0 sha256sum | sed 's#  \./#  #' ) > "$WORK/sums.txt"
# requires: minimum PHP version (from composer.json) and extensions (like the installer), checked by the admin
# before updating from the panel
EXTENSIONS="$(grep -o "EXTENSIONS = \[[^]]*\]" "$PKG/core/app/Support/Install/Requirements.php" | grep -o "'[a-z_]*'" | tr -d "'" | paste -sd, -)"
python3 - "$WORK/sums.txt" "$PKG/core/manifest.json" "$VERSION" "$PKG/core/composer.json" "$EXTENSIONS" <<'PY'
import json, re, sys
sums, out, ver, composer, extensions = sys.argv[1:]
files = {}
for line in open(sums, encoding="utf-8"):
    h, p = line.rstrip("\n").split("  ", 1)
    files[p] = h
php = re.search(r"(\d+\.\d+(?:\.\d+)?)", json.load(open(composer))["require"]["php"]).group(1)
requires = {"php": php, "extensions": [e for e in extensions.split(",") if e]}
json.dump({"name": "resume-app", "version": ver, "requires": requires, "files": files}, open(out, "w"), indent=1, sort_keys=True)
PY

echo "== Zip"
rm -f "$OUT/$NAME.zip"   # zip updates an existing archive: always start from scratch
( cd "$PKG" && zip -qr -X "$OUT/$NAME.zip" . )
( cd "$OUT" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" )
echo "Created $OUT/$NAME.zip ($(du -h "$OUT/$NAME.zip" | cut -f1))"
