<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * robots.txt generato: indica la sitemap con l'indirizzo reale del sito.
 * Il percorso dell'admin non viene elencato (sarebbe un'indicazione in più per chi lo cerca):
 * le sue pagine hanno già X-Robots-Tag: noindex.
 */
class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
