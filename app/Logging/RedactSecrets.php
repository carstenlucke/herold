<?php

namespace App\Logging;

use Illuminate\Log\Logger;

class RedactSecrets
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(new SecretRedactionProcessor);
    }
}
