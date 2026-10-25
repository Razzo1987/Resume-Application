<?php

/**
 * Resume App - estrazione di un pacchetto di aggiornamento, fuori da Laravel.
 *
 * L'admin prepara l'aggiornamento (controlli, copia dei contenuti, manutenzione), copia questo file in
 * core/storage/framework/resume-updater.php e scrive lo stato in core/storage/framework/resume-update.json.
 * Finché lo stato esiste, index.php passa ogni richiesta a resume_updater_handle() senza avviare l'app,
 * perché i suoi file vengono sostituiti proprio in quel momento:
 *  - chi ha il codice dell'aggiornamento (nell'indirizzo o nel cookie resume_update, cioè l'amministratore
 *    che l'ha avviato) vede l'avanzamento; la pagina chiama a ripetizione ?resume-update=CODICE&step=1 (POST),
 *    che estrae un blocco di file per volta (pochi secondi, sotto i limiti di tempo degli hosting) e si può
 *    riprendere dopo un errore;
 *  - tutti gli altri ricevono 503 "Sito in aggiornamento".
 * Alla fine lo stato viene cancellato e il browser torna all'admin, che completa l'aggiornamento
 * (migrazioni, cache, manutenzione): vedi App\Support\Update\UpdateRunner.
 *
 * Solo funzioni di PHP: niente classi dell'app né di Composer. Il formato dello stato e la firma di
 * resume_updater_handle() non devono cambiare tra le versioni (index.php nuovo, updater della versione vecchia).
 */

declare(strict_types=1);

if (! function_exists('resume_updater_handle')) {
    /**
     * Gestisce la richiesta corrente e termina lo script.
     */
    function resume_updater_handle(string $stateFile, string $webroot, string $core): never
    {
        $state = resume_updater_read($stateFile);
        $given = (string) ($_GET['resume-update'] ?? $_COOKIE['resume_update'] ?? '');
        $authorized = $state !== null && $given !== '' && hash_equals((string) $state['token'], $given);

        if (! $authorized) {
            resume_updater_unavailable($state);
        }

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_GET['step'])) {
            try {
                $result = resume_updater_step($stateFile, $webroot, $core);
                $status = 200;

                if ($result['done']) {
                    resume_updater_cookie('', time() - 3600);
                }
            } catch (Throwable $exception) {
                $result = ['error' => $exception->getMessage()];
                $status = 500;
            }

            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        // L'indirizzo con il codice fa da "chiave": il cookie permette di tornare qui anche da altre pagine
        if (isset($_GET['resume-update'])) {
            resume_updater_cookie((string) $state['token'], 0);
        }

        resume_updater_page($state);
    }

    /**
     * Estrae il blocco successivo di file e salva l'avanzamento.
     *
     * @return array{next: int, total: int, done: bool, redirect: ?string}
     */
    function resume_updater_step(string $stateFile, string $webroot, string $core, float $budget = 4.0, int $max = 2500): array
    {
        $lock = fopen($stateFile.'.lock', 'c');

        if ($lock === false || ! flock($lock, LOCK_EX)) {
            throw new RuntimeException('Lock not available.');
        }

        $finished = false;

        try {
            $state = resume_updater_read($stateFile);

            if ($state === null) {
                throw new RuntimeException('Update state not readable.');
            }

            $zip = new ZipArchive;

            if ($zip->open((string) $state['zip'], ZipArchive::RDONLY) !== true) {
                throw new RuntimeException('Package not readable: '.basename((string) $state['zip']));
            }

            $total = $zip->numFiles;
            $next = (int) $state['next'];
            $start = microtime(true);
            $count = 0;

            while ($next < $total && $count < $max && (microtime(true) - $start) < $budget) {
                resume_updater_extract($zip, $next, $webroot, $core);
                $next++;
                $count++;
            }

            $zip->close();
            $state['next'] = $next;
            $finished = $next >= $total;

            if ($finished) {
                // Da qui l'app riparte: la richiesta successiva arriva a Laravel
                @unlink($stateFile);
            } else {
                resume_updater_write($stateFile, $state);
            }

            return ['next' => $next, 'total' => $total, 'done' => $finished, 'redirect' => $finished ? (string) $state['return'] : null];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);

            if ($finished) {
                @unlink($stateFile.'.lock');
            }
        }
    }

    /**
     * Scrive un elemento del pacchetto: prima in un file temporaneo, poi lo sostituisce all'originale.
     */
    function resume_updater_extract(ZipArchive $zip, int $index, string $webroot, string $core): void
    {
        $name = (string) $zip->getNameIndex($index);
        $target = resume_updater_target($name, $webroot, $core);

        if ($target === null) {
            return;
        }

        if (str_ends_with($name, '/')) {
            resume_updater_mkdir($target);

            return;
        }

        resume_updater_mkdir(dirname($target));

        $input = $zip->getStreamIndex($index);
        $temporary = $target.'.resume-tmp';
        $output = @fopen($temporary, 'wb');

        if ($input === false || $output === false) {
            throw new RuntimeException('Cannot write '.$target);
        }

        $bytes = stream_copy_to_stream($input, $output);
        fclose($input);
        fclose($output);

        $stat = $zip->statIndex($index);

        if ($stat === false || $bytes !== (int) $stat['size']) {
            @unlink($temporary);

            throw new RuntimeException('Incomplete file: '.$name);
        }

        if (! @rename($temporary, $target)) {
            @unlink($temporary);

            throw new RuntimeException('Cannot replace '.$target);
        }

        @chmod($target, 0644);

        if (str_ends_with($target, '.php') && function_exists('opcache_invalidate')) {
            @opcache_invalidate($target, true);
        }
    }

    /**
     * Percorso di destinazione di un elemento del pacchetto, o null se non va scritto (dati del sito).
     * "core/..." va nella cartella dell'app (anche fuori dalla webroot, con core-path.php), il resto nella webroot.
     */
    function resume_updater_target(string $name, string $webroot, string $core): ?string
    {
        if (! resume_updater_safe_name($name)) {
            throw new RuntimeException('File name not allowed in the package: '.$name);
        }

        $isDirectory = str_ends_with($name, '/');
        $path = rtrim($name, '/');

        if ($path === 'core') {
            return $core;
        }

        [$area, $relative, $base] = str_starts_with($path, 'core/')
            ? ['core', substr($path, 5), $core]
            : ['web', $path, $webroot];

        return resume_updater_protected($area, $relative, $isDirectory) ? null : $base.'/'.$relative;
    }

    function resume_updater_safe_name(string $name): bool
    {
        return $name !== ''
            && ! str_contains($name, "\0")
            && ! str_contains($name, '\\')
            && ! str_starts_with($name, '/')
            && preg_match('#^[A-Za-z]:#', $name) !== 1
            && preg_match('#(^|/)\.\.?(/|$)#', $name) !== 1;
    }

    /**
     * Dati e configurazione del sito che un pacchetto non deve mai sovrascrivere.
     */
    function resume_updater_protected(string $area, string $relative, bool $isDirectory): bool
    {
        // Temi installati dall'admin (il pacchetto contiene solo "base")
        if (preg_match('#^themes/(?!base(/|$))#', $relative) === 1) {
            return true;
        }

        if ($area === 'core') {
            return $relative === '.env' || (str_starts_with($relative, 'storage/') && ! $isDirectory);
        }

        return in_array($relative, ['.htaccess', 'core-path.php'], true)
            || $relative === 'uploads'
            || str_starts_with($relative, 'uploads/');
    }

    function resume_updater_mkdir(string $directory): void
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create '.$directory);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    function resume_updater_read(string $stateFile): ?array
    {
        $state = json_decode((string) @file_get_contents($stateFile), true);

        return is_array($state) && isset($state['token'], $state['zip'], $state['next']) ? $state : null;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    function resume_updater_write(string $stateFile, array $state): void
    {
        $temporary = $stateFile.'.tmp';

        if (@file_put_contents($temporary, json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false || ! @rename($temporary, $stateFile)) {
            throw new RuntimeException('Cannot save the update state.');
        }
    }

    function resume_updater_cookie(string $value, int $expires): void
    {
        $https = ($_SERVER['HTTPS'] ?? '') !== '' && ($_SERVER['HTTPS'] ?? '') !== 'off';

        setcookie('resume_update', $value, ['expires' => $expires, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    }

    /**
     * @param  array<string, mixed>|null  $state
     */
    function resume_updater_text(?array $state, string $key, string $default): string
    {
        $text = $state['strings'][$key] ?? null;

        return htmlspecialchars(is_string($text) ? $text : $default, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Pagina per i visitatori durante l'estrazione.
     *
     * @param  array<string, mixed>|null  $state
     */
    function resume_updater_unavailable(?array $state): never
    {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        header('Retry-After: 120');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");

        $title = resume_updater_text($state, 'visitor_title', 'Site update in progress');
        $text = resume_updater_text($state, 'visitor_text', 'Please come back in a few minutes.');
        $lang = resume_updater_text($state, 'visitor_lang', 'en');

        echo <<<HTML
            <!doctype html>
            <html lang="{$lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title}</title>
            <style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;color:#1f2937;background:#f3f4f6}main{max-width:32rem;padding:2rem;text-align:center}h1{font-size:1.5rem}</style>
            </head><body><main><h1>{$title}</h1><p>{$text}</p></main></body></html>
            HTML;
        exit;
    }

    /**
     * Pagina con l'avanzamento, per chi ha avviato l'aggiornamento.
     *
     * @param  array<string, mixed>  $state
     */
    function resume_updater_page(array $state): never
    {
        $nonce = base64_encode(random_bytes(16));

        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        header('Referrer-Policy: no-referrer');
        header("Content-Security-Policy: default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");

        $t = fn (string $key, string $default): string => resume_updater_text($state, $key, $default);
        $config = json_encode([
            'token' => (string) $state['token'],
            'next' => (int) $state['next'],
            'total' => (int) ($state['total'] ?? 0),
            'retry' => $t('retry', 'Retry'),
            'done' => $t('done', 'Files updated, completing the update...'),
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
        $lang = $t('lang', 'en');
        $title = $t('title', 'Updating');
        $version = htmlspecialchars((string) ($state['from'] ?? '').' → '.(string) ($state['to'] ?? ''), ENT_QUOTES, 'UTF-8');
        $intro = $t('intro', 'The new files are being written. Keep this page open: it takes a minute or two.');
        $error = $t('error', 'The update stopped:');

        echo <<<HTML
            <!doctype html>
            <html lang="{$lang}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
            <title>{$title}</title>
            <style nonce="{$nonce}">
            body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.5 system-ui,sans-serif;color:#1f2937;background:#f3f4f6}
            main{width:min(34rem,100% - 2rem);padding:2rem;background:#fff;border-radius:.75rem;box-shadow:0 1px 3px rgba(0,0,0,.1)}
            h1{margin:0 0 .25rem;font-size:1.35rem}.version{margin:0 0 1rem;color:#6b7280}
            progress{width:100%;height:1rem}.count{margin:.5rem 0 0;color:#6b7280;font-variant-numeric:tabular-nums}
            .error{display:none;margin-top:1rem;padding:.75rem;border-radius:.5rem;color:#991b1b;background:#fee2e2}
            .error.on{display:block}button{margin-top:.75rem;padding:.5rem 1rem;border:0;border-radius:.5rem;color:#fff;background:#334155;font:inherit;cursor:pointer}
            </style></head><body><main>
            <h1>{$title}</h1><p class="version">{$version}</p><p>{$intro}</p>
            <progress id="bar" max="1" value="0"></progress><p class="count" id="count" aria-live="polite"></p>
            <div class="error" id="error" role="alert"><span>{$error}</span> <span id="message"></span><br><button type="button" id="retry"></button></div>
            </main>
            <script nonce="{$nonce}">
            (() => {
                const config = {$config};
                const url = location.pathname + '?resume-update=' + encodeURIComponent(config.token) + '&step=1';
                const bar = document.getElementById('bar'), count = document.getElementById('count');
                const box = document.getElementById('error'), message = document.getElementById('message'), retry = document.getElementById('retry');
                retry.textContent = config.retry;
                const show = (next, total) => { if (total > 0) { bar.max = total; bar.value = next; count.textContent = next + ' / ' + total; } };
                show(config.next, config.total);
                async function run() {
                    box.classList.remove('on');
                    let failures = 0;
                    for (;;) {
                        let data;
                        try {
                            const response = await fetch(url, { method: 'POST', credentials: 'same-origin', cache: 'no-store' });
                            data = await response.json();
                        } catch (exception) {
                            data = { error: String(exception) };
                        }
                        if (data.error) {
                            // Un blocco interrotto dal server (tempo massimo) si riprende da solo, poi si chiede all'utente
                            if (++failures < 4) { await new Promise((resolve) => setTimeout(resolve, 1500 * failures)); continue; }
                            message.textContent = data.error; box.classList.add('on'); return;
                        }
                        failures = 0;
                        show(data.next, data.total);
                        if (data.done) { count.textContent = config.done; location.href = data.redirect; return; }
                    }
                }
                retry.addEventListener('click', run);
                run();
            })();
            </script></body></html>
            HTML;
        exit;
    }
}
