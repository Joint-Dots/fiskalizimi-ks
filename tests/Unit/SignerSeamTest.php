<?php

declare(strict_types=1);

namespace Jointdots\FiskalizimiKs\Tests\Unit;

use Jointdots\FiskalizimiKs\Engine\Signer;
use Jointdots\FiskalizimiKs\Engine\SignerInterface;
use Jointdots\FiskalizimiKs\Tests\TestCase;

class SignerSeamTest extends TestCase
{
    public function test_default_binding_is_openssl_signer(): void
    {
        $this->assertInstanceOf(Signer::class, app(SignerInterface::class));
    }
}
