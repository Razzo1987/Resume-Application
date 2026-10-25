#!/usr/bin/env bash
# Crea il pacchetto completo di rilascio: releases/resume-app-<versione>.zip
#
# Struttura dello zip (si estrae TUTTO nella webroot, es. public_html/):
#   index.php, htaccess.dist, build/, themes/…   ← contenuto di public/
#   core/                                        ← resto dell'app (protetto da core/.htaccess)
#   core/VERSION, core/manifest.json
#
# Il pacchetto non contiene mai dati: .env, storage/ (solo struttura vuota), public/uploads, temi PRO.
# Uso: scripts/build-release.sh [cartella-output]   (dalla radice del repo, con working tree pulito)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="${1:-$ROOT/../releases}"
VERSION="$(tr -d '[:space:]' < "$ROOT/VERSION")"
NAME="resume-app-${VERSION}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

echo "== Esportazione dei soli file versionati"
mkdir -p "$WORK/src"
git -C "$ROOT" archive --format=tar HEAD | tar -x -C "$WORK/src"

echo "== Dipendenze di produzione e asset"
( cd "$WORK/src"
  composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
  npm ci --no-audit --no-fund
  npm run build
  rm -rf node_modules tests .github .editorconfig phpunit.xml phpstan.neon* pint.json \
         package.json package-lock.json vite.config.* playwright.config.* scripts
)

echo "== Composizione: public/ → radice, resto → core/"
PKG="$WORK/$NAME"
mkdir -p "$PKG/core"
shopt -s dotglob
mv "$WORK/src/public/"* "$PKG/"
rmdir "$WORK/src/public"
mv "$WORK/src/"* "$PKG/core/"
shopt -u dotglob
[ -f "$PKG/.htaccess" ] && rm "$PKG/.htaccess"        # .htaccess non si sovrascrive mai
cp "$PKG/core/stubs/htaccess.dist" "$PKG/htaccess.dist"
cp "$PKG/core/stubs/core.htaccess" "$PKG/core/.htaccess"
rm -rf "$PKG/uploads" "$PKG/core/.env" "$PKG/core/.env.example.local"
# storage: solo la struttura (le cartelle con il loro .gitignore), nessun dato
find "$PKG/core/storage" -type f ! -name .gitignore -delete

echo "== Manifest (per rimuovere i file orfani negli aggiornamenti)"
echo "$VERSION" > "$PKG/core/VERSION"
( cd "$PKG" && find . -type f ! -path './core/manifest.json' -print0 | sort -z \
  | xargs -0 sha256sum | sed 's#  \./#  #' ) > "$WORK/sums.txt"
python3 - "$WORK/sums.txt" "$PKG/core/manifest.json" "$VERSION" <<'PY'
import json, sys
sums, out, ver = sys.argv[1:]
files = {}
for line in open(sums, encoding="utf-8"):
    h, p = line.rstrip("\n").split("  ", 1)
    files[p] = h
json.dump({"name": "resume-app", "version": ver, "files": files}, open(out, "w"), indent=1, sort_keys=True)
PY

echo "== Zip"
mkdir -p "$OUT"
( cd "$PKG" && zip -qr -X "$OUT/$NAME.zip" . )
( cd "$OUT" && sha256sum "$NAME.zip" > "$NAME.zip.sha256" )
echo "Creato $OUT/$NAME.zip ($(du -h "$OUT/$NAME.zip" | cut -f1))"
