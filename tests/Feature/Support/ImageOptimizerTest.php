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
