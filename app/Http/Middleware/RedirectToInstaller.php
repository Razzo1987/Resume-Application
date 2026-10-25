<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Finché l'applicazione non è installata (manca il .env), ogni pagina porta all'installer.
 */
class RedirectToInstaller
{
    public function __construct(private readonly InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->state->isInstalled() && ! $request->is('install')) {
            return redirect()->to(url($this->hasRewrite() ? 'install' : 'index.php/install'));
        }

        return $next($request);
    }

    /**
     * Prima installazione: il pacchetto non contiene .htaccess (lo scrive l'installer), quindi gli indirizzi
     * "belli" funzionano solo se c'è già una regola verso index.php (es. sviluppo). Altrimenti /index.php/install.
     */
    private function hasRewrite(): bool
    {
        $htaccess = public_path('.htaccess');

        return is_file($htaccess) && str_contains((string) file_get_contents($htaccess), 'index.php');
    }
}
