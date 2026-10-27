#!/usr/bin/env bash
# Prima creazione del Codespace: dipendenze, asset, .env, database con il CV di prova e un amministratore demo.
set -euo pipefail

set_env() { # set_env CHIAVE valore
    if grep -q "^$1=" .env; then sed -i "s#^$1=.*#$1=$2#" .env; else echo "$1=$2" >> .env; fi
}

composer install --no-interaction --no-progress
npm ci --no-audit --no-fund
npm run build

[ -f .env ] || cp .env.example .env
set_env APP_ENV production
set_env APP_DEBUG false
set_env DB_HOST db
set_env DB_DATABASE resume_app
set_env DB_USERNAME resume
set_env DB_PASSWORD resume
grep -q '^APP_KEY=base64' .env || php artisan key:generate --force

echo "Attendo il database..."
for _ in $(seq 1 90); do
    php -r 'try { new PDO("mysql:host=db;dbname=resume_app", "resume", "resume"); } catch (Throwable) { exit(1); }' && break
    sleep 2
done

php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoSeeder --force
# Versione registrata come dopo l'installer (altrimenti l'admin chiede di completare l'aggiornamento)
php artisan tinker --execute='app(App\Support\System\Updater::class)->complete();' >/dev/null
php artisan make:filament-user --name="Demo" --email=demo@example.com --password=demo-password
