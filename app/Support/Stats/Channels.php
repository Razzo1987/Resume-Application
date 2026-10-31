<?php

declare(strict_types=1);

namespace App\Support\Stats;

/**
 * Channel of a visit, from what the browser already sent (referring site, UTM parameters, platform click codes):
 * direct, search engines, social networks, AI assistants, webmail, other sites, campaigns. The lists of domains
 * are part of the app: nothing is looked up elsewhere.
 */
final class Channels
{
    /** In the order shown on the Statistics page */
    public const array ALL = ['direct', 'search', 'social', 'ai', 'email', 'sites', 'campaign'];

    /** AI assistants (checked first: gemini.google.com is not Google search) */
    private const array AI = [
        'chatgpt.com', 'chat.openai.com', 'openai.com', 'perplexity.ai', 'claude.ai', 'gemini.google.com', 'bard.google.com',
        'copilot.microsoft.com', 'copilot.cloud.microsoft', 'chat.deepseek.com', 'deepseek.com', 'chat.mistral.ai', 'mistral.ai',
        'meta.ai', 'grok.com', 'x.ai', 'poe.com', 'you.com', 'phind.com', 'chat.qwen.ai', 'kimi.com', 'kimi.ai',
    ];

    /** Webmail (checked before search and social: mail.google.com, mail.yahoo.com) */
    private const array EMAIL = [
        'mail.google.com', 'outlook.live.com', 'outlook.office.com', 'outlook.office365.com', 'mail.yahoo.com', 'mail.aol.com',
        'mail.libero.it', 'mail.virgilio.it', 'webmail.aruba.it', 'mail.proton.me', 'mail.zoho.com', 'gmx.net', 'web.de', 'mail.ru',
    ];

    private const array SEARCH = [
        'bing.com', 'duckduckgo.com', 'ecosia.org', 'qwant.com', 'startpage.com', 'search.brave.com', 'yandex.ru', 'yandex.com',
        'ya.ru', 'baidu.com', 'naver.com', 'seznam.cz', 'ask.com', 'aol.com', 'mojeek.com', 'kagi.com', 'sogou.com', 'so.com',
        'search.yahoo.com', 'yahoo.com', 'search.libero.it', 'virgilio.it', 'lilo.org', 'swisscows.com', 'metager.org',
    ];

    private const array SOCIAL = [
        'linkedin.com', 'lnkd.in', 'facebook.com', 'fb.com', 'fb.me', 'messenger.com', 'instagram.com', 't.co', 'x.com',
        'twitter.com', 'reddit.com', 'youtube.com', 'youtu.be', 'tiktok.com', 'pinterest.com', 'bsky.app', 'threads.net',
        'threads.com', 'mastodon.social', 'mastodon.online', 'telegram.org', 't.me', 'whatsapp.com', 'news.ycombinator.com',
        'xing.com', 'tumblr.com', 'quora.com', 'discord.com', 'snapchat.com', 'vk.com',
    ];

    /** Platform click codes that mean "paid advertising" (the others come from social networks) */
    private const array AD_CLICKS = ['google-ads', 'microsoft-ads', 'yandex'];

    public static function of(?string $referrer, ?string $utmSource, ?string $utmMedium, ?string $utmCampaign, ?string $clickSource): string
    {
        if ($utmSource !== null || $utmMedium !== null || $utmCampaign !== null || in_array($clickSource, self::AD_CLICKS, true)) {
            return 'campaign';
        }

        if ($referrer === null || $referrer === '') {
            return $clickSource !== null ? 'social' : 'direct';
        }

        $host = strtolower($referrer);

        return match (true) {
            self::matches($host, self::AI) => 'ai',
            self::matches($host, self::EMAIL) || str_starts_with($host, 'webmail.') => 'email',
            self::matches($host, self::SEARCH) || preg_match('/(^|\.)google\.[a-z]{2,3}(\.[a-z]{2})?$/', $host) === 1 => 'search',
            self::matches($host, self::SOCIAL) => 'social',
            default => 'sites',
        };
    }

    /**
     * The host is one of the domains or a subdomain of one (l.facebook.com, it.linkedin.com).
     *
     * @param  list<string>  $domains
     */
    private static function matches(string $host, array $domains): bool
    {
        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
