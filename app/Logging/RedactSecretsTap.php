<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Logger as MonologLogger;

/** Laravel "tap" that attaches the RedactSecrets processor to a log channel. */
final class RedactSecretsTap
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if ($monolog instanceof MonologLogger) {
            $monolog->pushProcessor(new RedactSecrets);
        }
    }
}
