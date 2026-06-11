<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class FiscalResult
{
    public function __construct(
        public readonly int          $journalId,
        public readonly string       $verificationNo,
        public readonly string       $citizenQr,
        public readonly int          $fiscalTime,
        public readonly ?int         $transactionNo,
        public readonly FiscalStatus $status,
        public readonly ?string      $error = null,
    ) {}
}
