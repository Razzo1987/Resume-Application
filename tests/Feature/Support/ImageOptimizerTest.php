<?php

use App\Support\Media\ImageOptimizer;
use Illuminate\Support\Facades\Storage;

it('resizes images and converts them to WebP', function () {
    Storage::fake('uploads');

    $source = tempnam(sys_get_temp_dir(), 'img');
    $image = imagecreatetruecolor(3000, 2000);
    imagepng($image, $source);

    $path = app(ImageOptimizer::class)->store($source, 'Logo Azienda.png', 'uploads', 'logos', 600);

    expect($path)->toStartWith('logos/logo-azienda-')->toEndWith('.webp');

    $size = getimagesizefromstring(Storage::disk('uploads')->get($path));

    expect($size[0])->toBe(600)->and($size[1])->toBe(400)->and($size['mime'])->toBe('image/webp');
});

it('keeps small images at their size', function () {
    Storage::fake('uploads');

    $source = tempnam(sys_get_temp_dir(), 'img');
    imagejpeg(imagecreatetruecolor(200, 100), $source);

    $path = app(ImageOptimizer::class)->store($source, 'foto.jpg', 'uploads', 'profile');

    expect(getimagesizefromstring(Storage::disk('uploads')->get($path))[0])->toBe(200);
});

it('saves the images it cannot convert with the extension of their real type, never the one of the upload', function (string $name) {
    Storage::fake('uploads');

    // Animated GIF (two 1x1 frames): left as it is, but a name like "x.html" must not turn it into a page of the site
    $source = tempnam(sys_get_temp_dir(), 'img');
    $frame = '21F904000A0000002C0000000001000100000202440100';
    file_put_contents($source, hex2bin('474946383961010001008000'.'00FFFFFF000000'.$frame.$frame.'3B'));

    $path = app(ImageOptimizer::class)->store($source, $name, 'uploads', 'logos');

    expect($path)->toEndWith('.gif')->and(Storage::disk('uploads')->exists($path))->toBeTrue();
})->with(['logo.html', 'logo.svg', 'logo.pht', 'logo.php', 'logo']);

it('refuses files that are not images', function () {
    Storage::fake('uploads');

    $source = tempnam(sys_get_temp_dir(), 'img');
    file_put_contents($source, '<html><script>alert(1)</script></html>');

    expect(fn () => app(ImageOptimizer::class)->store($source, 'logo.png', 'uploads', 'logos'))->toThrow(RuntimeException::class)
        ->and(Storage::disk('uploads')->allFiles())->toBe([]);
});
