<?php

namespace Jointdots\FiskalizimiKs\Exceptions;

use RuntimeException;

class FiscalSubmissionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
