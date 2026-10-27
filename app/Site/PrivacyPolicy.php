<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Profile;
use App\Site\Contact\Cloak;
use App\Site\Content\RichText;
use App\Support\Settings\Settings;
use App\Support\Stats\GeoIp;
use Illuminate\Support\HtmlString;

/**
 * Privacy policy: "/{locale}/privacy" page with the link in the footer.
 *
 * It is enabled from the settings and is always enabled with visit statistics. The text is written per language
 * in the admin; when empty, the template text of the language pack applies (lang/{locale}/site.php, privacy.* keys).
 * In the text {name} becomes the profile name and {email} the address (encoded as in the rest of the site).
 * With statistics enabled, the part about statistics is added automatically at the end, always consistent with
 * the settings (retention months, geolocation).
 */
final class PrivacyPolicy
{
    public function __construct(
        private readonly Settings $settings,
        private readonly RichText $richText,
        private readonly Cloak $cloak,
        private readonly GeoIp $geoIp,
    ) {}

    public function isEnabled(): bool
    {
        return $this->settings->bool('privacy.page') || $this->statsEnabled();
    }

    public function statsEnabled(): bool
    {
        return $this->settings->bool('stats.enabled');
    }

    /**
     * Template text of a language, with {name} and {email} to replace (it can be copied into the admin editor).
     */
    public function template(string $locale): string
    {
        $html = '';

        foreach (['intro', 'cookies', 'logs', 'contact', 'rights'] as $part) {
            $html .= '<h2>'.e(__("site.privacy.{$part}_title", [], $locale)).'</h2>';
            $html .= '<p>'.e(__("site.privacy.{$part}", [], $locale)).'</p>';
        }

        return $html;
    }

    /**
     * The part about statistics, generated from the settings.
     */
    public function statsSection(string $locale): string
    {
        $paragraph = __('site.privacy.stats', [], $locale);

        if ($this->geoIp->isAvailable()) {
            $paragraph .= ' '.__('site.privacy.stats_zone', [], $locale);
        }

        $html = '<h2>'.e(__('site.privacy.stats_title', [], $locale)).'</h2>';
        $html .= '<p>'.e($paragraph).'</p>';
        $html .= '<p>'.e(__('site.privacy.stats_ip', [], $locale)).'</p>';
        $html .= '<p>'.e(__('site.privacy.stats_basis', ['months' => max(1, (int) $this->settings->get('stats.retention_months', 12))], $locale)).'</p>';

        if ($this->geoIp->isAvailable()) {
            $html .= '<p><small>'.e(__('site.privacy.stats_credit', [], $locale)).'</small></p>';
        }

        return $html;
    }

    /**
     * Text to show on the site: sanitized, with the name and email in place and, if needed, the statistics.
     */
    public function render(string $locale, Profile $profile): HtmlString
    {
        // No fallback to the main language: a language without its own text uses the template in its language
        $texts = $this->settings->get('privacy.text', []);
        $custom = is_array($texts) && is_string($texts[$locale] ?? null) ? $texts[$locale] : null;
        $html = (string) $this->richText->render($this->richText->hasText($custom) ? $custom : $this->template($locale));

        if ($this->statsEnabled()) {
            $html .= $this->statsSection($locale);
        }

        return new HtmlString(strtr($html, [
            '{name}' => e($profile->full_name),
            '{email}' => $this->email($locale, $profile),
        ]));
    }

    /**
     * Email as in the rest of the site: encoded in the HTML and put back together by the theme script.
     */
    private function email(string $locale, Profile $profile): string
    {
        if (! $this->settings->bool('privacy.show_email') || blank($profile->email)) {
            return e(__('site.privacy.contact_fallback', [], $locale));
        }

        return sprintf(
            '<a class="js-cloak" href="#privacy" data-cloak-href="%s" data-cloak-text="%s"><span class="cloak-hint">%s</span></a>',
            e($this->cloak->encode('mailto:'.$profile->email)),
            e($this->cloak->encode((string) $profile->email)),
            e(__('site.contact.reveal_hint', [], $locale)),
        );
    }
}
