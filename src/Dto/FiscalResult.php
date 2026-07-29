<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class FiscalResult
{
    public function __construct(
        public readonly int          $journalId,
        public readonly string       $verificationNo,
        public readonly string       $citizenQr,
        public readonly int          $fiscalTime,
        /** ATK's uint64 transaction number, as a decimal string. */
        public readonly ?string      $transactionNo,
        public readonly FiscalStatus $status,
        public readonly ?string      $error = null,
    ) {}
}
