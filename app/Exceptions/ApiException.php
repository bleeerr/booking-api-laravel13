<?php

namespace App\Exceptions;

use App\Enums\ApiErrorCode;
use RuntimeException;

class ApiException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        public readonly ApiErrorCode $errorCode,
        string $message,
        public readonly int $httpStatus,
        public readonly array $details = [],
    ) {
        parent::__construct($message);
    }
}
