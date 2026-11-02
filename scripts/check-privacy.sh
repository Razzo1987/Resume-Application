#!/usr/bin/env bash
# Privacy guard: personal data and private code must never reach the public repository or the release packages.
#
# Usage: scripts/check-privacy.sh [folder]
#   without a folder: the files tracked by git in this repository;
#   with a folder: every file in it (e.g. an extracted release package).
#
# Generic rules (always): themes other than "base", Font Awesome Pro, environment files, private keys.
# Maintainer's rules: PRIVACY_PATTERNS, one extended regular expression per line (case-insensitive), kept in a
# repository secret so the personal data itself is never written in the code. Lines starting with # are ignored.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TARGET="${1:-}"
SELF="scripts/check-privacy.sh"
FAILED=0

fail() {
    echo "::error::$1"
    FAILED=1
}

# List of files, relative to the scanned folder
if [ -z "$TARGET" ]; then
    cd "$ROOT"
    mapfile -t FILES < <(git ls-files)
else
    cd "$TARGET"
    mapfile -t FILES < <(find . -type f -printf '%P\n' | sort)
fi

echo "== ${#FILES[@]} files in $(pwd)"

# 1) File names
for file in "${FILES[@]}"; do
    case "$file" in
        themes/base/*|core/themes/base/*) ;;
        # Repository: themes/{name}; package: core/themes/{name} and the compiled assets in themes/{name}
        themes/*/*|core/themes/*/*) fail "Theme other than base: $file" ;;
    esac
    case "$(basename "$file")" in
        .env|.env.*)
            [ "$(basename "$file")" = ".env.example" ] || fail "Environment file: $file" ;;
        *.pem|*.key|*.p12|*.pfx|id_rsa|id_ed25519|*.gpg|auth.json)
            fail "Key or credentials file: $file" ;;
    esac
done

# 2) Content (text files only)
GENERIC='@fortawesome/(pro-|fontawesome-pro)|fontawesome-pro|kit\.fontawesome\.com|pro\.fontawesome\.com|npm\.fontawesome\.com|FONTAWESOME_(PACKAGE|NPM_AUTH)_TOKEN'
PATTERNS_FILE="$(mktemp)"
trap 'rm -f "$PATTERNS_FILE"' EXIT
printf '%s\n' "$GENERIC" > "$PATTERNS_FILE"

if [ -n "${PRIVACY_PATTERNS:-}" ]; then
    printf '%s\n' "$PRIVACY_PATTERNS" | sed -e 's/\r$//' -e '/^[[:space:]]*$/d' -e '/^[[:space:]]*#/d' >> "$PATTERNS_FILE"
    echo "== Maintainer's rules: $(($(wc -l < "$PATTERNS_FILE") - 1))"
else
    echo "::notice::PRIVACY_PATTERNS is not set: only the generic rules are checked"
fi

SCAN=()
for file in "${FILES[@]}"; do
    [ "$file" = "$SELF" ] && continue
    [ -f "$file" ] && SCAN+=("$file")
done

if [ "${#SCAN[@]}" -gt 0 ]; then
    # -I: binary files skipped; only file and line are printed, never the matching text
    MATCHES="$(printf '%s\0' "${SCAN[@]}" | xargs -0 grep -I -n -i -E -f "$PATTERNS_FILE" -- 2>/dev/null | cut -d: -f1,2 || true)"

    if [ -n "$MATCHES" ]; then
        while IFS= read -r match; do
            fail "Personal data or private code at $match"
        done <<< "$MATCHES"
    fi
fi

if [ "$FAILED" -ne 0 ]; then
    echo "Privacy guard: FAILED"
    exit 1
fi

echo "Privacy guard: OK"
