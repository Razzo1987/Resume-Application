<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Cron\Cron;
use App\Support\Stats\AdminIps;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagine dell'admin con un amministratore collegato: annota il PHP del sito (per gli aggiornamenti automatici)
 * e l'IP dell'amministratore (le sue visite al sito non contano nelle statistiche) e, se il cron dell'hosting non c'è, fa partire i lavori programmati leggeri dopo aver risposto.
 */
class AdminActivity
{
    public function __construct(
        private readonly Cron $cron,
        private readonly AdminIps $adminIps,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            rescue(function () use ($request): void {
                $this->cron->rememberWebRuntime();
                $this->adminIps->touch($request->ip());

                if (! app()->runningUnitTests()) {
                    $this->cron->runFromAdminIfNeeded();
                }
            }, report: false);
        }

        return $response;
    }
}
