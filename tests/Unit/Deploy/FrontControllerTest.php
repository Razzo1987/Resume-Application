<?php

/*
 * public/index.php must find the app code in three layouts:
 *  - release package: webroot/core
 *  - development (Laravel layout): project/public → project
 *  - core outside the webroot, set in core-path.php
 */

function fakeCore(string $dir): void
{
    is_dir("{$dir}/bootstrap") || mkdir("{$dir}/bootstrap", 0777, true);
    is_dir("{$dir}/vendor") || mkdir("{$dir}/vendor", 0777, true);

    file_put_contents("{$dir}/vendor/autoload.php", <<<'PHP'
        <?php
        namespace Illuminate\Http { class Request { public static function capture(): self { return new self; } } }
        namespace Illuminate\Foundation { class Application {} }
        PHP);

    file_put_contents("{$dir}/bootstrap/app.php", <<<'PHP'
        <?php
        return new class {
            private string $public = '';
            public function usePublicPath(string $path): static { $this->public = $path; return $this; }
            public function handleRequest(object $request): void { echo realpath(__DIR__.'/..').'|'.$this->public; }
        };
        PHP);
}

function runFrontController(string $webroot): string
{
    $index = dirname(__DIR__, 3).'/public/index.php';
    copy($index, "{$webroot}/index.php");

    $output = shell_exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg("{$webroot}/index.php").' 2>&1');

    return trim((string) $output);
}

function tempRoot(): string
{
    $dir = sys_get_temp_dir().'/resume-fc-'.bin2hex(random_bytes(4));
    mkdir($dir, 0777, true);

    return realpath($dir);
}

it('uses ./core in the release layout', function () {
    $webroot = tempRoot();
    fakeCore("{$webroot}/core");

    expect(runFrontController($webroot))->toBe("{$webroot}/core|{$webroot}");
});

it('uses the parent folder in the development layout', function () {
    $project = tempRoot();
    mkdir("{$project}/public");
    fakeCore($project);

    expect(runFrontController("{$project}/public"))->toBe("{$project}|{$project}/public");
});

it('uses the path in core-path.php when the core lives outside the webroot', function () {
    $root = tempRoot();
    mkdir("{$root}/public_html");
    fakeCore("{$root}/private-core");
    file_put_contents("{$root}/public_html/core-path.php", "<?php return '{$root}/private-core';");

    expect(runFrontController("{$root}/public_html"))->toBe("{$root}/private-core|{$root}/public_html");
});

it('answers 503 with a clear message when the core is missing', function () {
    $webroot = tempRoot();

    expect(runFrontController($webroot))->toContain('application core not found');
});
