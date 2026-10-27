# Resume App in GitHub Codespaces

The Codespace starts with the sample CV of "John Doe" (Italian and English) already loaded.

- If VS Code asks *Do you trust the authors of the files in this folder?*, choose **Trust Folder & Continue**:
  until then the web server cannot start.
- **Site**: it opens by itself when the Codespace is ready; otherwise the *Ports* tab → port 8000 → globe icon.
  The web server runs in the *server* terminal (bottom panel): keep it open.
  Port 8000 is made public, so the link also works in other browsers; to keep it to yourself: *Ports* tab,
  right click on 8000, *Port Visibility*, *Private*.
- **Admin**: the same address followed by `/admin`, user `demo@example.com`, password `demo-password`.

- **Statistics**: the page shows sample visits; real ones are added when you open the site. Codespaces port forwarding
  does not pass on the visitor's address (everybody arrives from the same private address), so visits are counted
  without country and city, and yours do not count only while the browser is signed in to the admin.

Everything runs inside the Codespace and is deleted with it: feel free to change any content.

Useful commands in the terminal:

```bash
php artisan migrate:fresh --seed --force && php artisan db:seed --class=DemoSeeder --force   # sample CV again
php artisan make:filament-user --name="Demo" --email=demo@example.com --password=demo-password
bash .devcontainer/start.sh            # start the web server again (if you closed its terminal)
```
