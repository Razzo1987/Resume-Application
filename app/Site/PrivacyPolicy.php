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
 * Informativa privacy: pagina "/{lingua}/privacy" con il link nel footer.
 *
 * Si attiva dalle impostazioni ed è sempre attiva con le statistiche delle visite. Il testo si scrive per lingua
 * nell'admin; vuoto, vale il testo modello del pacchetto di lingua (lang/{lingua}/site.php, chiavi privacy.*).
 * Nel testo {name} diventa il nome del profilo ed {email} l'indirizzo (codificato come nel resto del sito).
 * Con le statistiche attive in fondo si aggiunge da sé la parte sulle statistiche, sempre coerente con le
 * impostazioni (mesi di conservazione, geolocalizzazione).
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
     * Testo modello di una lingua, con {name} ed {email} da sostituire (lo si può copiare nell'editor dell'admin).
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
     * Parte sulle statistiche, generata dalle impostazioni.
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
     * Testo da mostrare nel sito: sanificato, con nome ed email al loro posto e, se servono, le statistiche.
     */
    public function render(string $locale, Profile $profile): HtmlString
    {
        // Niente ripiego sulla lingua principale: una lingua senza testo proprio usa il modello nella sua lingua
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
     * Email come nel resto del sito: codificata nell'HTML e ricomposta dallo script del tema.
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
