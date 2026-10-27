<?php

declare(strict_types=1);

namespace App\Support\Update;

use RuntimeException;

/**
 * Fine anticipata del giro del cron: i file dell'app sono appena stati sostituiti (o l'estrazione si è fermata
 * a metà), quindi questo processo non deve eseguire altri lavori né caricare altre classi.
 */
final class CronStop extends RuntimeException
{
    public function __construct(string $message, public readonly bool $ok = true)
    {
        parent::__construct($message);
    }
}
