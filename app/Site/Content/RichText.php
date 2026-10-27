<?php

declare(strict_types=1);

namespace App\Site\Content;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Texts written with the admin editor and shown on the site.
 * The HTML is always sanitized: no scripts, event handlers, inline styles (also blocked by the CSP) or dangerous URLs.
 */
final class RichText
{
    private readonly HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $this->sanitizer = new HtmlSanitizer(
            (new HtmlSanitizerConfig)
                ->allowSafeElements()
                ->allowRelativeLinks()
                ->allowRelativeMedias()
                ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
                ->allowMediaSchemes(['https', 'http'])
                ->allowAttribute('width', 'img')
                ->allowAttribute('height', 'img')
                ->dropAttribute('style', '*')
                ->forceAttribute('a', 'rel', 'noopener')
                ->withMaxInputLength(500_000),
        );
    }

    /**
     * Safe HTML, or null if the text is empty.
     */
    public function render(?string $html): ?HtmlString
    {
        if ($html === null || ! $this->hasText($html)) {
            return null;
        }

        return new HtmlString($this->sanitizer->sanitize($html));
    }

    /**
     * Plain text (for meta description, JSON-LD, previews), optionally shortened.
     */
    public function plain(?string $html, int $limit = 0): string
    {
        if ($html === null) {
            return '';
        }

        // Line breaks between blocks become spaces before the tags are stripped
        $text = preg_replace('#<(br|/p|/li|/h[1-6]|/div|/blockquote|/tr)\b[^>]*>#i', ' $0', $html) ?? $html;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $limit > 0 ? Str::limit($text, $limit, '...', preserveWords: true) : $text;
    }

    public function hasText(?string $html): bool
    {
        return $html !== null && (trim(strip_tags($html)) !== '' || str_contains($html, '<img'));
    }
}
