# Resume App in GitHub Codespaces

The Codespace starts with the sample CV of "John Doe" (Italian and English) already loaded.

- **Site**: it opens by itself when the Codespace is ready; otherwise the *Ports* tab → port 8000 → globe icon.
  The web server runs in the *server* terminal (bottom panel): keep it open.
- **Admin**: the same address followed by `/admin`, user `demo@example.com`, password `demo-password`.

Everything runs inside the Codespace and is deleted with it: feel free to change any content.

Useful commands in the terminal:

```bash
php artisan migrate:fresh --seed --force && php artisan db:seed --class=DemoSeeder --force   # sample CV again
php artisan make:filament-user --name="Demo" --email=demo@example.com --password=demo-password
bash .devcontainer/start.sh            # start the web server again (if you closed its terminal)
```
