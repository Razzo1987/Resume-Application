#!/usr/bin/env bash
# First creation of the Codespace: dependencies, assets, .env, database with the sample CV and a demo administrator.
set -euo pipefail

set_env() { # set_env KEY value
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

echo "Waiting for the database..."
for _ in $(seq 1 90); do
    php -r 'try { new PDO("mysql:host=db;dbname=resume_app", "resume", "resume"); } catch (Throwable) { exit(1); }' && break
    sleep 2
done

php artisan migrate:fresh --seed --force
php artisan db:seed --class=DemoSeeder --force
php artisan db:seed --class=DemoStatsSeeder --force   # fake visits to see the Statistics page
# Version recorded as after the installer (otherwise the admin asks to complete the update)
php artisan tinker --execute='app(App\Support\System\Updater::class)->complete();' >/dev/null
php artisan make:filament-user --name="Demo" --email=demo@example.com --password=demo-password
