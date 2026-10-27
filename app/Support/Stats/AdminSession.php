<?php

declare(strict_types=1);

namespace App\Support\Stats;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Recognizes the administrator on the public site from the browser signed in to the admin: the admin session cookie
 * (path "/") also reaches the public pages. Visitors never get cookies from this site, so for them nothing is read;
 * for the administrator the session is only read, never started or saved.
 *
 * It works whatever the address: also behind a proxy that gives every visitor the same IP (GitHub Codespaces) or
 * with an address shared with other people, where the exclusion by IP would not work.
 */
class AdminSession
{
    public function __construct(private readonly Encrypter $encrypter) {}

    public function isSignedIn(Request $request): bool
    {
        // The admin uses the default session guard
        try {
            $guard = Auth::guard();
            $recaller = $guard->getRecallerName();
            $key = $guard->getName();
        } catch (Throwable) {
            return false;
        }

        // "Remember me" cookie: only the administrators get it
        if ($this->cookie($request, $recaller) !== null) {
            return true;
        }

        $id = $this->cookie($request, (string) config('session.cookie'));

        if ($id === null || strlen($id) !== 40 || ! ctype_alnum($id)) {
            return false;
        }

        try {
            // A copy of the configured session store (same handler, same encryption), emptied and pointed at this id
            /** @var Store $store */
            $store = clone app('session')->driver();
            $store->flush();
            $store->setId($id);
            $store->start();

            return $store->get($key) !== null;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Value of a cookie encrypted by Laravel (EncryptCookies), or null if missing or not valid.
     */
    private function cookie(Request $request, string $name): ?string
    {
        $value = $request->cookies->get($name);

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            $decrypted = $this->encrypter->decrypt($value, false);
        } catch (Throwable) {
            return null;
        }

        return is_string($decrypted) ? CookieValuePrefix::validate($name, $decrypted, $this->encrypter->getAllKeys()) : null;
    }
}
