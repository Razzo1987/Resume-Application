<?php

/*
 * Trusted proxies (read by Laravel's TrustProxies middleware).
 *
 * Only needed if the site sits behind a proxy that changes the request address: CDN or tunnel (Cloudflare),
 * GitHub Codespaces port forwarding, some hosting providers. Then the address, protocol and visitor IP must be
 * taken from the X-Forwarded-* headers, otherwise, for example, after login you end up back on "localhost".
 *
 * RESUME_TRUSTED_PROXIES: empty (default, no proxy), "*" (any proxy) or a comma-separated list of IPs or networks
 * (e.g. "10.0.0.1,192.168.0.0/16"). With "*" and no proxy in front of the site anyone could fake the IP and
 * the address with the headers: use it only if the server can be reached exclusively through the proxy.
 */
return [
    'proxies' => env('RESUME_TRUSTED_PROXIES') ?: null,
];
