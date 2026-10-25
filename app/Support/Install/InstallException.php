<?php

declare(strict_types=1);

namespace App\Support\Install;

use RuntimeException;

/**
 * Errore dell'installazione con un messaggio da mostrare all'utente.
 */
class InstallException extends RuntimeException {}
