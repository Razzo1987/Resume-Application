#!/usr/bin/env bash
# Compila un tema (icone + asset) e, con --zip, crea il pacchetto installabile dall'admin.
#
#   scripts/build-theme.sh <cartella-del-tema> [--icons <sorgente icone>] [--zip <cartella output>]
#
# La cartella del tema può stare fuori dal repository (es. ../themes-pro/luca-pro): viene copiata
# in themes/{nome} (ignorata da git), compilata in public/themes/{nome} e impacchettata così:
#   {nome}/theme.json, icons.php, views/, lang/, README.md, LICENSE*   → core/themes/{nome}
#   {nome}/dist/                                                       → webroot/themes/{nome}
# I sorgenti (resources/) non entrano nel pacchetto.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:?cartella del tema}"; shift
SRC="$(cd "$SRC" && pwd)"
ICONS=""; ZIP_OUT=""
while [ $# -gt 0 ]; do
  case "$1" in
    --icons) ICONS="$(cd "$2" && pwd)"; shift 2 ;;
    --zip) mkdir -p "$2"; ZIP_OUT="$(cd "$2" && pwd)"; shift 2 ;;
    *) echo "Opzione sconosciuta: $1" >&2; exit 1 ;;
  esac
done

NAME="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).name)' "$SRC/theme.json")"
VERSION="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).version)' "$SRC/theme.json")"
[[ "$NAME" =~ ^[a-z0-9][a-z0-9-]*$ ]] && [ "$NAME" != base ] || { echo "Nome di tema non valido: $NAME" >&2; exit 1; }

cd "$ROOT"
if [ "$SRC" != "$ROOT/themes/$NAME" ]; then
  echo "== Copia del tema in themes/$NAME"
  rm -rf "themes/$NAME"
  mkdir -p "themes/$NAME"
  ( cd "$SRC" && tar --exclude=node_modules --exclude=.git --exclude=dist -cf - . ) | tar -xf - -C "themes/$NAME"
fi

echo "== Icone"
if [ -n "$ICONS" ]; then node scripts/build-icons.mjs "$NAME" "$ICONS"; else node scripts/build-icons.mjs "$NAME"; fi

echo "== Asset"
THEME="$NAME" npx vite build

if [ -n "$ZIP_OUT" ]; then
  echo "== Pacchetto"
  WORK="$(mktemp -d)"; trap 'rm -rf "$WORK"' EXIT
  PKG="$WORK/$NAME"
  mkdir -p "$PKG/dist"
  ( cd "themes/$NAME" && for f in theme.json icons.php README.md LICENSE LICENSE.txt LICENSE.md views lang; do
      [ -e "$f" ] && cp -R "$f" "$PKG/"; done; true )
  cp -R "public/themes/$NAME/." "$PKG/dist/"
  find "$PKG" -name '.DS_Store' -delete
  rm -f "$ZIP_OUT/$NAME-$VERSION.zip"
  ( cd "$WORK" && zip -qr -X "$ZIP_OUT/$NAME-$VERSION.zip" "$NAME" )
  ( cd "$ZIP_OUT" && sha256sum "$NAME-$VERSION.zip" > "$NAME-$VERSION.zip.sha256" )
  echo "Creato $ZIP_OUT/$NAME-$VERSION.zip ($(du -h "$ZIP_OUT/$NAME-$VERSION.zip" | cut -f1))"
fi
