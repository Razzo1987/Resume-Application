# Screenshots

Resume App with the open `base` theme and the sample CV of "John Doe" (the demo content you can load during the
installation). The images are taken by an end-to-end test (`tests/e2e/screenshots.spec.js`) on every push: the
latest ones are attached to the [CI workflow runs](https://github.com/Razzo1987/Resume-Application/actions/workflows/ci.yml)
as the `screenshots` artifact.

## Public site

| Desktop | Phone |
|---|---|
| ![CV page](screenshots/cv-desktop.webp) | ![CV page on a phone](screenshots/cv-mobile.webp) |

The CV page, top to bottom (click to open it full size):

<a href="screenshots/cv-full-page.webp"><img src="screenshots/cv-full-page.webp" alt="The whole CV page" width="480"></a>

| Project page | Experience page |
|---|---|
| ![Project page](screenshots/project.webp) | ![Experience page](screenshots/experience.webp) |

| Blog | Article |
|---|---|
| ![Blog](screenshots/blog.webp) | ![Blog article](screenshots/article.webp) |

![Experience section on a phone](screenshots/cv-mobile-experience.webp)

## Admin panel

| Dashboard | Editing an experience (one tab per language) |
|---|---|
| ![Dashboard](screenshots/admin-dashboard.webp) | ![Experience form](screenshots/admin-experience.webp) |

| CV layout | Translations |
|---|---|
| ![CV layout](screenshots/admin-cv-layout.webp) | ![Translations](screenshots/admin-translations.webp) |

| Settings | Themes |
|---|---|
| ![Settings](screenshots/admin-settings.webp) | ![Themes](screenshots/admin-themes.webp) |

## Taking them again

```bash
php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder
php artisan make:filament-user --name=Demo --email=demo@example.com --password=demo-password
RESUME_ADMIN_LOCALE=en php artisan serve       # in another terminal
SCREENSHOTS=1 E2E_BASE_URL=http://localhost:8000 npx playwright test screenshots --project=chromium
```

The PNG files are written to `screenshots/`; the WebP versions in `docs/screenshots/` are smaller copies of them.
