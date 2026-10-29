#!/usr/bin/env bash
# Builds a theme (icons + assets) and, with --zip, creates the package that can be installed from the admin.
#
#   scripts/build-theme.sh <theme-folder> [--icons <icon source>] [--zip <output folder>]
#
# The theme folder can live outside the repository (e.g. ../my-themes/fancy): it is copied
# to themes/{name} (ignored by git), built into public/themes/{name} and packaged like this:
#   {name}/theme.json, icons.php, views/, lang/, README.md, LICENSE*   → core/themes/{name}
#   {name}/dist/                                                       → webroot/themes/{name}
# The sources (resources/) are not included in the package.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SRC="${1:?theme folder}"; shift
SRC="$(cd "$SRC" && pwd)"
ICONS=""; ZIP_OUT=""
while [ $# -gt 0 ]; do
  case "$1" in
    --icons) ICONS="$(cd "$2" && pwd)"; shift 2 ;;
    --zip) mkdir -p "$2"; ZIP_OUT="$(cd "$2" && pwd)"; shift 2 ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
done

NAME="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).name)' "$SRC/theme.json")"
VERSION="$(node -e 'console.log(JSON.parse(require("fs").readFileSync(process.argv[1],"utf8")).version)' "$SRC/theme.json")"
[[ "$NAME" =~ ^[a-z0-9][a-z0-9-]*$ ]] && [ "$NAME" != base ] || { echo "Invalid theme name: $NAME" >&2; exit 1; }

cd "$ROOT"
if [ "$SRC" != "$ROOT/themes/$NAME" ]; then
  echo "== Copying the theme to themes/$NAME"
  rm -rf "themes/$NAME"
  mkdir -p "themes/$NAME"
  ( cd "$SRC" && tar --exclude=node_modules --exclude=.git --exclude=.github --exclude=dist -cf - . ) | tar -xf - -C "themes/$NAME"
fi

echo "== Icons"
if [ -n "$ICONS" ]; then node scripts/build-icons.mjs "$NAME" "$ICONS"; else node scripts/build-icons.mjs "$NAME"; fi

echo "== Assets"
THEME="$NAME" npx vite build

if [ -n "$ZIP_OUT" ]; then
  echo "== Package"
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
  echo "Created $ZIP_OUT/$NAME-$VERSION.zip ($(du -h "$ZIP_OUT/$NAME-$VERSION.zip" | cut -f1))"
fi
