<?php

declare(strict_types=1);

namespace App\Themes;

use RuntimeException;

/**
 * Errore di installazione o rimozione di un tema, con un messaggio da mostrare all'amministratore.
 */
class ThemeException extends RuntimeException {}
