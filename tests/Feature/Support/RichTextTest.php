<?php

use App\Site\Content\RichText;

it('keeps the formatting allowed by the editors', function () {
    $html = (string) app(RichText::class)->render('<h2>Titolo</h2><p><strong>Grassetto</strong> e <a href="https://example.com">link</a></p><ul><li>Uno</li></ul><table><tr><td>1</td></tr></table><img src="/uploads/a.webp" width="10" height="5" alt="A">');

    expect($html)->toContain('<h2>Titolo</h2>')
        ->toContain('<strong>Grassetto</strong>')
        ->toContain('<a href="https://example.com" rel="noopener">link</a>')
        ->toContain('<li>Uno</li>')
        ->toContain('<td>1</td>')
        ->toContain('<img src="/uploads/a.webp" width="10" height="5" alt="A" />');
});

it('removes scripts, event handlers, inline styles and dangerous URLs', function () {
    $html = (string) app(RichText::class)->render('<p style="color:red" onclick="x()">Ok<script>alert(1)</script><a href="javascript:alert(1)">a</a><iframe src="https://evil"></iframe></p>');

    expect($html)->toContain('Ok')
        ->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('style=')
        ->not->toContain('javascript:')
        ->not->toContain('iframe');
});

it('treats empty editor content as missing', function () {
    $richText = app(RichText::class);

    expect($richText->render(null))->toBeNull()
        ->and($richText->render('<p></p>'))->toBeNull()
        ->and($richText->render('<p> <br> </p>'))->toBeNull();
});

it('extracts plain text for descriptions', function () {
    expect(app(RichText::class)->plain('<p>Uno</p><p>Due &amp; tre</p><ul><li>quattro</li></ul>'))->toBe('Uno Due & tre quattro')
        ->and(app(RichText::class)->plain('<p>'.str_repeat('parola ', 50).'</p>', 30))->toBe('parola parola parola parola...');
});
