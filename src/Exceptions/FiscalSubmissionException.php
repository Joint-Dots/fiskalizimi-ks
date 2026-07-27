<?php

namespace Jointdots\FiskalizimiKs\Exceptions;

use RuntimeException;

class FiscalSubmissionException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly bool $retryable = true,
        int $code = 0,
        /**
         * ATK received the submission but its outcome could not be read, so the
         * coupon may already be recorded there. Distinct from a rejection, which
         * is a verdict: an unknown result must never be resolved by assuming one.
         */
        public readonly bool $unknown = false,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
