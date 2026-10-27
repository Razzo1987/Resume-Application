<?php

use App\Themes\ThemeManager;

function makeThemesFixture(): string
{
    $root = sys_get_temp_dir().'/resume-themes-'.bin2hex(random_bytes(4));
    $GLOBALS['themes_fixture'] = $root;

    $files = [
        'base/theme.json' => json_encode(['name' => 'base', 'title' => 'Base', 'entrypoints' => ['themes/base/app.js']]),
        'base/views/home.blade.php' => 'base home',
        'base/views/footer.blade.php' => 'base footer',
        'base/icons.php' => "<?php return ['briefcase' => 'fa-solid fa-briefcase', 'github' => 'fa-brands fa-github'];",
        'pro/theme.json' => json_encode(['name' => 'pro', 'title' => 'Pro', 'extends' => 'base']),
        'pro/views/home.blade.php' => 'pro home',
        'pro/icons.php' => "<?php return ['briefcase' => 'fal fa-briefcase'];",
        'public/themes/pro/manifest.json' => '{}',
        'nobuild/theme.json' => json_encode(['name' => 'nobuild', 'extends' => 'base']),
    ];

    foreach ($files as $path => $content) {
        is_dir(dirname("{$root}/{$path}")) || mkdir(dirname("{$root}/{$path}"), 0777, true);
        file_put_contents("{$root}/{$path}", $content);
    }

    return $root;
}

function publicFixture(): string
{
    return $GLOBALS['themes_fixture'].'/public';
}

it('builds the inheritance chain from the active theme to the base theme', function () {
    $manager = new ThemeManager(makeThemesFixture(), 'pro', 'base', publicFixture());

    expect(array_map(fn ($theme) => $theme->name, $manager->chain()))->toBe(['pro', 'base']);
});

it('looks for views in the active theme first, then in the base theme', function () {
    $root = makeThemesFixture();
    $manager = new ThemeManager($root, 'pro', 'base', "{$root}/public");

    expect($manager->viewPaths())->toBe(["{$root}/pro/views", "{$root}/base/views"]);
});

it('merges icons, letting the active theme override the base ones', function () {
    $manager = new ThemeManager(makeThemesFixture(), 'pro', 'base', publicFixture());

    expect($manager->icon('briefcase'))->toBe('fal fa-briefcase')
        ->and($manager->icon('github'))->toBe('fa-brands fa-github')
        ->and($manager->icon('unknown'))->toBeNull();
});

it('falls back to the base theme when the active theme is missing or invalid', function (string $active) {
    $root = makeThemesFixture();
    $manager = new ThemeManager($root, $active, 'base', "{$root}/public");

    expect($manager->active()->name)->toBe('base')
        ->and($manager->chain())->toHaveCount(1);
})->with(['missing', '../base', 'Pro', 'nobuild']);

it('reads entrypoints and the build directory from theme.json', function () {
    $theme = (new ThemeManager(makeThemesFixture(), 'base', 'base'))->active();

    expect($theme->entrypoints)->toBe(['themes/base/app.js'])
        ->and($theme->buildDirectory())->toBe('themes/base');
});
