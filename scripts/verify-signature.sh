#!/usr/bin/env bash
# Checks the Ed25519 signature (file.zip.sig, base64) of release packages against the public keys of the app
# (App\Support\Update\Signature::KEYS), the same check the app makes before installing an update. Used by the release
# workflow right after signing, so a wrong RELEASE_SIGNING_KEY never reaches a release.
#
#     bash scripts/verify-signature.sh dist/resume-app-2.4.0.zip [more.zip ...]
#
# SIGNING_PUBLIC_KEYS (base64 keys separated by spaces) replaces the keys of the app, for tests.
set -euo pipefail

[ $# -gt 0 ] || { echo "usage: $0 file.zip [...]" >&2; exit 2; }

keys="${SIGNING_PUBLIC_KEYS:-$(grep -oE "'[A-Za-z0-9+/]{43}='" "$(dirname "$0")/../app/Support/Update/Signature.php" | tr -d "'")}"
[ -n "$keys" ] || { echo "No public key found" >&2; exit 1; }

work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

# Raw Ed25519 public key (32 bytes) → PEM: DER prefix of an Ed25519 SubjectPublicKeyInfo + the key
index=0
for key in $keys; do
    { printf '\x30\x2a\x30\x05\x06\x03\x2b\x65\x70\x03\x21\x00'; printf '%s' "$key" | base64 -d; } > "$work/key-$index.der"
    openssl pkey -pubin -inform DER -in "$work/key-$index.der" -out "$work/key-$index.pem"
    index=$((index + 1))
done

for zip in "$@"; do
    [ -f "$zip.sig" ] || { echo "Missing signature: $zip.sig" >&2; exit 1; }
    base64 -d "$zip.sig" > "$work/signature.bin"
    ok=0
    for pem in "$work"/key-*.pem; do
        if openssl pkeyutl -verify -pubin -inkey "$pem" -rawin -in "$zip" -sigfile "$work/signature.bin" >/dev/null 2>&1; then
            ok=1
            break
        fi
    done
    [ "$ok" = 1 ] || { echo "Invalid signature: $zip (not made with a key of the app)" >&2; exit 1; }
    echo "Signature verified: $zip"
done
