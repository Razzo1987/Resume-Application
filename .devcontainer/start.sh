#!/usr/bin/env bash
# A ogni avvio del Codespace: indirizzo pubblico della porta 8000 e server PHP.
set -euo pipefail

if [ -n "${CODESPACE_NAME:-}" ]; then
    url="https://${CODESPACE_NAME}-8000.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
else
    url="http://localhost:8000"
fi

sed -i "s#^APP_URL=.*#APP_URL=${url}#" .env
php artisan optimize:clear >/dev/null

nohup php artisan serve --host=0.0.0.0 --port=8000 > storage/logs/serve.log 2>&1 &
echo "Resume App: ${url}  (admin: ${url}/admin - demo@example.com / demo-password)"
