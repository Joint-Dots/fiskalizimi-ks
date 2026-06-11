<?php

namespace Jointdots\FiskalizimiKs\Engine;

final class SignedPayload
{
    public function __construct(
        public readonly string $details,    // base64(serialized PosCoupon proto)
        public readonly string $signature,  // base64(ECDSA signature of $details)
    ) {}

    /** @return array{details: string, signature: string} */
    public function toRequestPayload(): array
    {
        return ['details' => $this->details, 'signature' => $this->signature];
    }
}
