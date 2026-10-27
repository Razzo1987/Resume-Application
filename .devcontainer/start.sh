#!/usr/bin/env bash
# Starts the site in the Codespace (postAttachCommand: runs in a VS Code terminal and stays open).
# Sets the public address of port 8000 and starts the PHP server in the foreground.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "${CODESPACE_NAME:-}" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
    url="http://localhost:8000"
fi

# Already running (e.g. a second window connected to the same Codespace)
if php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'; then
    echo "Resume App is already running: ${url}"
    exit 0
fi

set_env() { # set_env KEY value
    if grep -q "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else echo "$1=$2" >> .env; fi
}
set_env APP_URL "$url"
# Codespaces port forwarding is a proxy: public address and visitor IP in the X-Forwarded-* headers
set_env RESUME_TRUSTED_PROXIES '*'
php artisan optimize:clear >/dev/null

# Port 8000 is made public, so the site also opens outside the GitHub session
# (e.g. from another browser or for whoever gets the link). Needs the GitHub CLI, included in the container.
if [ -n "${CODESPACE_NAME:-}" ] && command -v gh >/dev/null; then
    ( sleep 5; gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" >/dev/null 2>&1 \
        || echo "Port 8000 is private: Ports tab, right click, Port Visibility, Public." ) &
fi

echo
echo "Resume App: ${url}"
echo "Admin:      ${url}/admin  (demo@example.com / demo-password)"
echo "Stop the server with Ctrl+C, start it again with: bash .devcontainer/start.sh"
echo

# Built-in PHP server with 4 workers
PHP_CLI_SERVER_WORKERS=4 exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
