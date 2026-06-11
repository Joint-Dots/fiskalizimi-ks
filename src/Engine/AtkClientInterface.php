<?php

namespace Jointdots\FiskalizimiKs\Engine;

use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;

interface AtkClientInterface
{
    /**
     * Submit a signed coupon payload to ATK and return the transaction number.
     *
     * @throws FiscalSubmissionException
     */
    public function submit(SignedPayload $payload, FiscalConfig $config): int;
}
