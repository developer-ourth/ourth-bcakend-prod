<?php

namespace App\Services\Zoho;

use RuntimeException;

class ZohoApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $zohoCode = 0, public readonly int $httpStatus = 0)
    {
        parent::__construct($message);
    }

    /** HTTP 429: per-minute (44), daily (45) or concurrency (1070) limit. */
    public function isRateLimited(): bool
    {
        return $this->httpStatus === 429 || in_array($this->zohoCode, [44, 45, 1070], true);
    }
}
