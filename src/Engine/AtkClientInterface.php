<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;

interface AtkClientInterface
{
    /**
     * Submit a signed coupon payload to ATK and return the transaction number.
     *
     * The number is a uint64 and is carried as a decimal string: its upper half
     * exceeds PHP's signed int, so an int return silently wrapped roughly every
     * other value negative and lost the one identifier ATK can be queried by.
     *
     * @throws FiscalSubmissionException
     */
    public function submit(SignedPayload $payload, FiscalConfig $config): string;
}
