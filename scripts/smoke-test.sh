#!/usr/bin/env bash
# Installs a release package on a real web server and checks what must hold on every hosting: the web installer
# works, the application files and the data are never served, PHP never runs from uploads/, no cookies on the public
# site, security headers. Used by the release workflow with Apache (scripts/apache/), also usable by hand.
#
#     bash scripts/smoke-test.sh http://127.0.0.1:8080 /path/to/webroot
#
# The webroot must contain the extracted package (with htaccess.dist copied to .htaccess) and be writable by the
# web server. Database: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD (an empty database).
# The installation uses the demo CV, so the end-to-end tests of the site can run on it afterwards.
set -euo pipefail

BASE="${1:?base URL}"
WEBROOT="${2:?webroot}"
FAILED=0
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

fail() { echo "::error::$1"; FAILED=1; }
ok() { echo "ok - $1"; }

# status <path>: HTTP status of a GET, without following redirects
status() { curl -s -o /dev/null -w '%{http_code}' --path-as-is "$BASE$1"; }

expect_denied() {
    local code
    code="$(status "$1")"
    case "$code" in 403|404) ok "$1 → $code" ;; *) fail "$1 is served (HTTP $code), it must be 403 or 404" ;; esac
}

echo "== Web installer"
[ "$(status /install)" = 200 ] || fail "/install does not answer 200"

curl -s -o "$WORK/install.html" -w '%{http_code}' -X POST "$BASE/install" \
    --data-urlencode "url=$BASE" \
    --data-urlencode "owner=Smoke Test" \
    --data-urlencode "email=admin@example.com" \
    --data-urlencode "password=smoke-test-password" \
    --data-urlencode "password_confirmation=smoke-test-password" \
    --data-urlencode "admin_path=admin" \
    --data-urlencode "default_language=en-GB" \
    --data-urlencode "languages[]=it-IT" \
    --data-urlencode "demo=1" \
    --data-urlencode "db_host=${DB_HOST:-127.0.0.1}" \
    --data-urlencode "db_port=${DB_PORT:-3306}" \
    --data-urlencode "db_database=${DB_DATABASE:-resume_app}" \
    --data-urlencode "db_username=${DB_USERNAME:-root}" \
    --data-urlencode "db_password=${DB_PASSWORD:-}" > "$WORK/install.status"

[ -f "$WEBROOT/core/.env" ] || { fail "installation failed (HTTP $(cat "$WORK/install.status"), no core/.env)"; grep -o 'class="[^"]*error[^"]*"[^<]*<[^<]*' "$WORK/install.html" | head -5 || true; exit 1; }
ok "installed (HTTP $(cat "$WORK/install.status"))"
expect_denied /install

echo "== Public site"
code="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' "$BASE/")"
case "$code" in 30[12]\ "$BASE"/en-GB*) ok "/ → ${code#* }" ;; *) fail "/ does not redirect to the default language ($code)" ;; esac

curl -s -D "$WORK/headers" -o "$WORK/home.html" "$BASE/en-GB"
grep -q '^HTTP/[0-9.]* 200' "$WORK/headers" || fail "/en-GB does not answer 200"
grep -qi '^set-cookie:' "$WORK/headers" && fail "the public site sets a cookie" || ok "no cookies on the public site"
for header in content-security-policy x-content-type-options referrer-policy; do
    grep -qi "^$header:" "$WORK/headers" && ok "header $header" || fail "header $header missing on the site"
done
grep -q 'John Doe' "$WORK/home.html" && ok "demo CV shown" || fail "the demo CV is not on the home page"

# An address starting with // must never lead to another site
code="$(curl -s -o /dev/null -w '%{http_code} %{redirect_url}' --path-as-is "$BASE//evil.example/")"
case "${code#* }" in http*://evil.example*|//evil.example*) fail "//evil.example/ redirects to another site ($code)" ;; *) ok "//evil.example/ stays on the site ($code)" ;; esac

echo "== Application files and data"
for path in /core/.env /core/VERSION /core/manifest.json /core/composer.json /core/artisan /core/storage/logs/laravel.log \
            /core/vendor/autoload.php /core-path.php /.htaccess /htaccess.dist /.env /core/themes/base/theme.json; do
    expect_denied "$path"
done

echo "== uploads/ never runs code"
mkdir -p "$WEBROOT/uploads"
printf '<?php echo "PHP-RAN-" . (6 * 7);' > "$WEBROOT/uploads/smoke-test.php"
printf '<html><script>alert(1)</script></html>' > "$WEBROOT/uploads/smoke-test.html"
body="$(curl -s "$BASE/uploads/smoke-test.php" || true)"
case "$body" in *PHP-RAN-42*) fail "PHP runs in uploads/" ;; *) ok "PHP does not run in uploads/ (HTTP $(status /uploads/smoke-test.php))" ;; esac
expect_denied /uploads/smoke-test.html
rm -f "$WEBROOT/uploads/smoke-test.php" "$WEBROOT/uploads/smoke-test.html"

echo "== Admin"
curl -s -D "$WORK/admin-headers" -o /dev/null "$BASE/admin/login"
grep -q '^HTTP/[0-9.]* 200' "$WORK/admin-headers" && ok "admin login page" || fail "/admin/login does not answer 200"
grep -qi '^x-frame-options:' "$WORK/admin-headers" && ok "admin header x-frame-options" || fail "header x-frame-options missing in the admin"

if [ "$FAILED" -ne 0 ]; then
    echo "Smoke test: FAILED"
    exit 1
fi

echo "Smoke test: OK"
