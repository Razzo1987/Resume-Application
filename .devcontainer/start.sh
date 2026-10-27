#!/usr/bin/env bash
# Avvio del sito nel Codespace (postAttachCommand: gira in un terminale di VS Code e resta aperto).
# Imposta l'indirizzo pubblico della porta 8000 e avvia il server PHP in primo piano.
set -euo pipefail
cd "$(dirname "$0")/.."

if [ -n "${CODESPACE_NAME:-}" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
    url="http://localhost:8000"
fi

# Già avviato (es. una seconda finestra collegata allo stesso Codespace)
if php -r 'exit(@fsockopen("127.0.0.1", 8000) ? 0 : 1);'; then
    echo "Resume App is already running: ${url}"
    exit 0
fi

sed -i "s#^APP_URL=.*#APP_URL=${url}#" .env
php artisan optimize:clear >/dev/null

# La porta 8000 diventa pubblica, così il sito si apre anche fuori dalla sessione di GitHub
# (es. da un altro browser o da chi riceve il link). Serve la GitHub CLI, inclusa nel container.
if [ -n "${CODESPACE_NAME:-}" ] && command -v gh >/dev/null; then
    ( sleep 5; gh codespace ports visibility 8000:public -c "$CODESPACE_NAME" >/dev/null 2>&1 \
        || echo "Port 8000 is private: Ports tab, right click, Port Visibility, Public." ) &
fi

echo
echo "Resume App: ${url}"
echo "Admin:      ${url}/admin  (demo@example.com / demo-password)"
echo "Stop the server with Ctrl+C, start it again with: bash .devcontainer/start.sh"
echo

# Server PHP integrato con 4 processi; il router sistema l'indirizzo pubblico (vedi router.php)
cd public
PHP_CLI_SERVER_WORKERS=4 exec php -S 0.0.0.0:8000 ../.devcontainer/router.php
