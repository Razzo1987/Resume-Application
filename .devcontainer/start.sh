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

echo
echo "Resume App: ${url}"
echo "Admin:      ${url}/admin  (demo@example.com / demo-password)"
echo "Stop the server with Ctrl+C, start it again with: bash .devcontainer/start.sh"
echo
PHP_CLI_SERVER_WORKERS=4 exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload
