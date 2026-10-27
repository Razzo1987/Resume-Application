<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Cron\Cron;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pagine dell'admin con un amministratore collegato: annota il PHP del sito (per gli aggiornamenti automatici)
 * e, se il cron dell'hosting non c'è, fa partire i lavori programmati leggeri dopo aver risposto.
 */
class AdminActivity
{
    public function __construct(private readonly Cron $cron) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->user() !== null) {
            rescue(function (): void {
                $this->cron->rememberWebRuntime();

                if (! app()->runningUnitTests()) {
                    $this->cron->runFromAdminIfNeeded();
                }
            }, report: false);
        }

        return $response;
    }
}
