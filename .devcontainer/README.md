# Resume App in GitHub Codespaces

The Codespace starts with the sample CV of "John Doe" (Italian and English) already loaded.

- **Site**: the *Ports* tab → port 8000 → open in browser (it may also open by itself).
- **Admin**: the same address followed by `/admin`, user `demo@example.com`, password `demo-password`.

Everything runs inside the Codespace and is deleted with it: feel free to change any content.

Useful commands in the terminal:

```bash
php artisan migrate:fresh --seed --force && php artisan db:seed --class=DemoSeeder --force   # sample CV again
php artisan make:filament-user --name="Demo" --email=demo@example.com --password=demo-password
bash .devcontainer/start.sh            # restart the web server
```
