<?php

declare(strict_types=1);

namespace App\Site;

use App\Models\Redirect;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Redirect gestiti dall'admin e creati in automatico quando cambia uno slug o un segmento URL.
 * Un redirect vale per il percorso indicato e per tutti i percorsi sotto di esso
 * (/it-IT/progetti → /it-IT/lavori sposta anche /it-IT/progetti/slug).
 */
final class Redirects
{
    /**
     * Destinazione per un percorso, oppure null.
     *
     * @return array{0: string, 1: int}|null [destinazione, codice HTTP]
     */
    public function match(string $path): ?array
    {
        // Si confronta il percorso decodificato (i redirect si scrivono "in chiaro", anche con spazi)
        $path = Redirect::normalizePath(rawurldecode($path));

        // Candidati: il percorso stesso e tutti i suoi "genitori" (/a/b/c, /a/b, /a)
        $segments = explode('/', trim($path, '/'));
        $candidates = [];

        for ($length = count($segments); $length >= 1; $length--) {
            $candidates[] = '/'.implode('/', array_slice($segments, 0, $length));
        }

        try {
            if (! Schema::hasTable('redirects')) {
                return null;
            }

            $redirect = Redirect::query()
                ->where('is_active', true)
                ->whereIn('from_path', $candidates)
                ->get()
                ->sortByDesc(fn (Redirect $redirect): int => strlen($redirect->from_path))
                ->first();
        } catch (Throwable) {
            return null;
        }

        if ($redirect === null) {
            return null;
        }

        $redirect->newQuery()->whereKey($redirect->getKey())->update([
            'hits' => $redirect->hits + 1,
            'last_hit_at' => now(),
        ]);

        $target = $redirect->to_path;

        // Redirect di un prefisso: si conserva il resto del percorso
        if ($redirect->from_path !== $path && ! str_starts_with($target, 'http')) {
            $rest = substr($path, strlen($redirect->from_path));
            $target = rtrim($target, '/').implode('/', array_map('rawurlencode', explode('/', $rest)));
        }

        return [$target, $redirect->status_code];
    }

    /**
     * Registra un redirect permanente evitando catene e cicli:
     * i redirect che puntavano a $from ora puntano a $to; un vecchio redirect da $to viene rimosso.
     */
    public function remember(string $from, string $to): void
    {
        $from = Redirect::normalizePath($from);
        $to = Redirect::normalizePath($to);

        if ($from === $to) {
            return;
        }

        Redirect::query()->where('from_path', $to)->delete();
        Redirect::query()->where('to_path', $from)->update(['to_path' => $to]);

        Redirect::query()->updateOrCreate(['from_path' => $from], ['to_path' => $to, 'status_code' => 301, 'is_active' => true]);
    }
}
