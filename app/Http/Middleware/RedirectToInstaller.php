<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Until the application is installed (the .env is missing), every page leads to the installer.
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
     * First installation: the package does not contain .htaccess (the installer writes it), so "pretty" URLs
     * work only if there is already a rule pointing to index.php (e.g. development). Otherwise /index.php/install.
     */
    private function hasRewrite(): bool
    {
        $htaccess = public_path('.htaccess');

        return is_file($htaccess) && str_contains((string) file_get_contents($htaccess), 'index.php');
    }
}
