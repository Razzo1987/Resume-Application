<?php

declare(strict_types=1);

namespace App\Support\Update;

use RuntimeException;

/**
 * Early end of the cron run: the app files have just been replaced (or the extraction stopped halfway), so this
 * process must not run any other jobs or load any other classes.
 */
final class CronStop extends RuntimeException
{
    public function __construct(string $message, public readonly bool $ok = true)
    {
        parent::__construct($message);
    }
}
