# Themes

Resume App ships with the open **`base`** theme. Other themes live in their own folders or repositories, extend
`base` and override only what they change: a few views, the SCSS, the fonts, the icons. They are installed from the
admin (*Themes* → *Install or update a theme*) as a zip and, if they say where they are published, updated from
there.

- [Folder of a theme](#folder-of-a-theme)
- [theme.json](#themejson)
- [Variants](#variants)
- [Icons](#icons)
- [Building and packaging](#building-and-packaging)
- [Packages with several themes](#packages-with-several-themes)
- [Updates from GitHub](#updates-from-github)

## Folder of a theme

```
fancy/
├── theme.json          # name, version, entry points, variants, update source
├── icons.php           # semantic icon name => classes of the icon set
├── views/              # only the views that change (the others come from base)
├── lang/               # optional strings of the theme: __('theme::file.key')
├── resources/          # sources: SCSS, JS, fonts, icon sources (not in the package)
└── README.md, LICENSE  # copied into the package
```

Views are resolved with the `theme::` namespace: the active theme first, then `base`. The rules of the public pages
still apply: no cookies, everything self-hosted (fonts included), no inline scripts or styles (CSP), personal data
printed only through the helpers of `base` (see the views of `base` as reference).

## theme.json

```json
{
    "name": "fancy",
    "title": "Fancy",
    "version": "1.0.0",
    "description": "Two columns, warm palette.",
    "extends": "base",
    "requires": { "resume-app": "^2.2" },
    "entrypoints": ["themes/fancy/resources/js/app.js"],
    "sprite": "themes/fancy/resources/icons/sprite.svg",
    "theme_color": "#2c3e50",
    "default_variant": "light",
    "variants": {
        "light": { "title": "Light", "entrypoints": ["themes/fancy/resources/scss/light.scss"], "theme_color": "#2c3e50" },
        "dark": { "title": "Dark", "entrypoints": ["themes/fancy/resources/scss/dark.scss"], "theme_color": "#121212" }
    },
    "updates": { "repository": "owner/fancy-themes", "tag_prefix": "fancy-v" },
    "author": "Your name",
    "license": "proprietary"
}
```

| Key | Meaning |
|---|---|
| `name` | Folder name: lowercase letters, digits and dashes. Also the name of the package (`fancy-1.0.0.zip`). |
| `version` | `X.Y.Z`. Compared with the releases on GitHub for updates. |
| `extends` | Parent theme (`base` if missing). A parent in the same package is installed first. |
| `requires.resume-app` | Compatible app versions (`^2.2`, `>=2.1 <3`). An incompatible theme is refused. |
| `entrypoints` | Vite entry points loaded on every page (usually the JS, or the SCSS when there are no variants). |
| `sprite` | Vite key of the SVG icon sprite built from `icons.php`. |
| `theme_color` | Color of the browser bar on phones (`meta theme-color`). |
| `variants`, `default_variant` | See [Variants](#variants). |
| `updates` | See [Updates from GitHub](#updates-from-github). |

## Variants

A theme can offer several **variants** of the same layout: palette and fonts, light or dark. Each variant has its
own entry points (usually one SCSS file that sets the variables and imports the shared styles) and, optionally, its
own `theme_color`. The pages load the theme's `entrypoints` plus those of the chosen variant.

The administrator picks the variant on the *Themes* page; without a choice the `default_variant` (or the first one)
is used. Every variant is compiled into the same package, so switching does not need a new installation.

## Icons

`icons.php` maps the semantic names used by the views and by the admin (`briefcase`, `email`, `linkedin`,
`cycling`...) to the classes of an icon set. Only the mapped icons end up in the sprite. Supported sets:

| Set | Classes | Source given to the build |
|---|---|---|
| Font Awesome Free (default) | `fa-solid fa-briefcase`, `fab fa-github` | none (from `node_modules`) |
| Font Awesome Pro (own licence) | `fal fa-briefcase`, `fad fa-chart-line` | the `sprites/` or `svgs/` folder of the kit |
| Tabler Icons (MIT) | `ti ti-briefcase`, `ti ti-heart-filled` | `node_modules/@tabler/icons/icons` |

A theme should map every name of `base` (see `themes/base/icons.php`): missing ones are taken from Font Awesome Free.
Icons distributed with a theme must allow it: Font Awesome Pro, for example, cannot be redistributed to others.

## Building and packaging

```bash
scripts/build-theme.sh ../my-themes/fancy --icons <icon source> --zip ../dist
```

The script copies the theme to `themes/fancy` (git-ignored), builds the icon sprite and the assets of the theme and
of all its variants, and creates `../dist/fancy-1.0.0.zip` with its `.sha256`. The package contains `theme.json`,
`icons.php`, `views/`, `lang/`, `README.md`, `LICENSE*` and the compiled assets in `dist/`; never the sources.

## Packages with several themes

A zip can contain several themes, each in its own folder:

```
fancy/theme.json, fancy/views/..., fancy/dist/...
plain/theme.json, plain/views/..., plain/dist/...
```

The admin installs or updates all of them in one go. Every theme is checked before anything is written: one wrong
file and nothing is installed. After the installation each theme is updated on its own (next section).

## Updates from GitHub

With `"updates": { "repository": "owner/name", "tag_prefix": "fancy-v" }` the app looks for the releases of the
theme on GitHub:

- tag `{tag_prefix}X.Y.Z` (for a repository with one theme the prefix can stay `v`);
- the package `{name}-X.Y.Z.zip` attached to the release, and optionally `{name}-X.Y.Z.zip.sha256` (checked);
- drafts and pre-releases are ignored.

Several themes can be published in the same repository, each with its own tag prefix: the app makes one request per
repository (conditional, so an unchanged answer does not count against the GitHub limit). The check runs with the
cron job *New theme versions* (every 30 minutes) or from *Themes* → *Check for theme updates*; the administrators
get one email per theme and version, and the *Themes* page shows *Update to X.Y.Z*.

**Private repositories** need the GitHub token of *System* → *Updates*: a fine-grained token with access to those
repositories and the permission *Contents: Read-only*. The same token still works for the app updates, because public
repositories can always be read.
