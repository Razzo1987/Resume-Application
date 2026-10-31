<?php

declare(strict_types=1);

namespace App\Support\Stats;

use App\Support\Settings\Settings;

/**
 * Kind of network a visit comes from, by the name of its owner (GeoLite ASN): a connection provider (home and
 * mobile lines: the visitor could be anybody), a data center or relay (cloud servers, VPNs, iCloud Private Relay) or
 * an organization with its own network (a company, a university, a public body: often a meaningful visit for a CV).
 * The lists below cover the most common providers; the administrator can mark other networks from the Statistics
 * page (stats.networks: name => provider|hosting|organization, which also overrides the lists).
 */
class Networks
{
    public const string PROVIDER = 'provider';

    public const string HOSTING = 'hosting';

    public const string ORGANIZATION = 'organization';

    /** Words in the names of connection providers (lowercase) */
    private const array PROVIDERS = [
        'telecom italia', 'tim s.p.a', 'vodafone', 'wind tre', 'windtre', 'fastweb', 'iliad', 'tiscali', 'eolo', 'linkem',
        'sky italia', 'open fiber', 'retelit', 'deutsche telekom', 'telekom deutschland', 'magenta telekom', 'orange', 'free sas',
        'free mobile', 'bouygues', 'societe francaise du radiotelephone', 'sfr sa', 'british telecommunications', 'virgin media',
        'sky uk', 'talktalk', 'ee limited', 'hutchison', 'telefonica', 'movistar', 'masmovil', 'digi spain', 'jazztel', 'euskaltel',
        'swisscom', 'sunrise', 'salt mobile', 'kpn', 'ziggo', 'proximus', 'telenet', 'a1 telekom', 'liberty global', 'unitymedia',
        'kabel deutschland', 'netcologne', 'm-net', '1&1', 'versatel', 'telia', 'telenor', 'elisa', 'tele2', 'nos comunicacoes',
        'meo', 'cosmote', 'turk telekom', 'turkcell', 'comcast', 'verizon', 'at&t', 'charter communications', 't-mobile',
        'cox communications', 'frontier communications', 'centurylink', 'lumen', 'spectrum', 'rogers communications', 'bell canada',
        'telus', 'shaw communications', 'videotron', 'telstra', 'optus', 'tpg telecom', 'bharti airtel', 'reliance jio',
        'claro', 'telmex', 'china telecom', 'china unicom', 'china mobile', 'kddi', 'softbank', 'ntt docomo', 'spacex',
        'starlink', 'rostelecom', 'megafon', 'beeline', 'kyivstar', 'orange polska', 'netia', 'magyar telekom', 'rcs & rds',
        'hrvatski telekom', 'telekom slovenije', 'singtel', 'chunghwa telecom', 'pldt', 'globe telecom', 'safaricom', 'mtn',
        'etisalat', 'emirates integrated', 'saudi telecom', 'vivo s.a', 'tim brasil', 'oi s.a',
    ];

    /** Words in the names of data centers, clouds and relays (lowercase) */
    private const array HOSTING_NAMES = [
        'amazon', 'digitalocean', 'ovh', 'hetzner', 'linode', 'akamai', 'cloudflare', 'fastly', 'oracle cloud', 'alibaba',
        'tencent', 'contabo', 'scaleway', 'online s.a.s', 'leaseweb', 'choopa', 'vultr', 'm247', 'datacamp', 'hostinger',
        'ionos', 'aruba s.p.a', 'servers.com', 'colocrossing', 'psychz', 'quadranet', 'zenlayer', 'g-core', 'gcore', 'netcup',
        'strato', 'hostroyale', 'packethub', 'clouvider', 'performive', 'mullvad', 'nordvpn', 'proton ag', 'private internet access',
    ];

    public function __construct(private readonly Settings $settings) {}

    public function kind(string $name): string
    {
        $marked = $this->marked();

        if (isset($marked[$name])) {
            return $marked[$name];
        }

        return match (true) {
            self::contains($name, self::HOSTING_NAMES) => self::HOSTING,
            self::contains($name, self::PROVIDERS) => self::PROVIDER,
            default => self::ORGANIZATION,
        };
    }

    /**
     * One of the words, as a whole word ("meo" is not in "Simeone").
     *
     * @param  list<string>  $words
     */
    private static function contains(string $name, array $words): bool
    {
        $pattern = '/(?<![\\p{L}\\p{N}])('.implode('|', array_map(fn (string $word): string => preg_quote($word, '/'), $words)).')(?![\\p{L}\\p{N}])/iu';

        return preg_match($pattern, $name) === 1;
    }

    /**
     * Marks a network as provider, data center or organization (null = back to the built-in lists).
     */
    public function mark(string $name, ?string $kind): void
    {
        $marked = $this->marked();

        if ($kind === null) {
            unset($marked[$name]);
        } else {
            $marked[$name] = $kind;
        }

        $this->settings->set('stats.networks', $marked);
    }

    /**
     * @return array<string, string>
     */
    public function marked(): array
    {
        $marked = $this->settings->get('stats.networks', []);

        return is_array($marked) ? array_filter($marked, fn (mixed $kind, mixed $name): bool => is_string($name) && in_array($kind, [self::PROVIDER, self::HOSTING, self::ORGANIZATION], true), ARRAY_FILTER_USE_BOTH) : [];
    }
}
