<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Engine\AtkClient;
use Jointdots\FiskalizimiKs\Engine\SignedPayload;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;
use PHPUnit\Framework\TestCase;

class AtkClientTest extends TestCase
{
    public function test_rate_limit_response_is_retryable(): void
    {
        $client = $this->clientWithResponse(new Response(
            429,
            ['Content-Type' => 'application/json'],
            '{"error":"rate limited"}'
        ));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail('Expected the ATK client to reject HTTP 429.');
        } catch (FiscalSubmissionException $e) {
            $this->assertTrue($e->retryable);
        }
    }

    public function test_bad_request_response_is_not_retryable(): void
    {
        $client = $this->clientWithResponse(new Response(
            400,
            ['Content-Type' => 'application/json'],
            '{"error":"invalid coupon"}'
        ));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail('Expected the ATK client to reject HTTP 400.');
        } catch (FiscalSubmissionException $e) {
            $this->assertFalse($e->retryable);
        }
    }

    public function test_transaction_id_is_returned_from_success_response(): void
    {
        $client = $this->clientWithResponse(new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"message":"ok","transaction_id":12345}'
        ));

        $transactionNo = $client->submit(
            new SignedPayload('details', 'signature'),
            $this->config()
        );

        $this->assertSame(12345, $transactionNo);
    }

    /**
     * A 2xx says ATK received the submission; an unparseable body says only that
     * we cannot read the outcome. ATK may already hold the coupon, so this is an
     * unknown result, not a verdict — recording a permanent rejection over a
     * possible acceptance is what invites a duplicate. It stays retryable because
     * the usual cause is an intercepting proxy or captive portal answering 200,
     * which resolves on its own.
     */
    public function test_success_without_a_transaction_number_is_an_unknown_result(): void
    {
        $client = $this->clientWithResponse(new Response(
            200,
            ['Content-Type' => 'text/html'],
            '<html><body>Sign in to continue</body></html>'
        ));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail('Expected the ATK client to report an unknown result.');
        } catch (FiscalSubmissionException $e) {
            $this->assertTrue($e->unknown, 'An unreadable 2xx must be flagged unknown.');
            $this->assertTrue($e->retryable, 'An unknown result must stay retryable.');
        }
    }

    /** A genuine rejection is a verdict, not an unknown result. */
    public function test_a_rejected_submission_is_not_flagged_unknown(): void
    {
        $client = $this->clientWithResponse(new Response(
            400,
            ['Content-Type' => 'application/json'],
            '{"message":"invalid coupon"}'
        ));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail('Expected the ATK client to reject HTTP 400.');
        } catch (FiscalSubmissionException $e) {
            $this->assertFalse($e->unknown);
        }
    }

    private function clientWithResponse(Response $response): AtkClient
    {
        $handler = HandlerStack::create(new MockHandler([$response]));

        return new AtkClient(new Client(['handler' => $handler]));
    }

    private function config(): FiscalConfig
    {
        return new FiscalConfig(1, 1, 1, 1, 'Test', 'unused.pem');
    }
}
