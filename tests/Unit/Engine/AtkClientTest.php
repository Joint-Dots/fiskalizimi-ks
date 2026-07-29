<?php

namespace Jointdots\FiskalizimiKs\Tests\Unit\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertSame('12345', $transactionNo);
    }

    /**
     * ATK's TransactionNo is a uint64. Everything above PHP_INT_MAX used to be
     * decoded as a float and then wrapped negative by an (int) cast, so roughly
     * half of all coupons recorded a transaction number ATK had never issued.
     */
    public function test_transaction_id_above_php_int_max_survives_intact(): void
    {
        $client = $this->clientWithResponse(new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"message":"ok","transaction_id":18446744073709551615}'
        ));

        $transactionNo = $client->submit(
            new SignedPayload('details', 'signature'),
            $this->config()
        );

        $this->assertSame('18446744073709551615', $transactionNo);
    }

    /**
     * A JSON float has already dropped the low digits of a uint64, so there is no
     * faithful number left to store. Holding the coupon as unknown beats filing
     * an identifier that matches nothing at ATK.
     */
    public function test_a_non_integer_transaction_number_is_an_unknown_result(): void
    {
        $client = $this->clientWithResponse(new Response(
            200,
            ['Content-Type' => 'application/json'],
            '{"message":"ok","transaction_id":1.8446744073709552e19}'
        ));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail('Expected a FiscalSubmissionException.');
        } catch (FiscalSubmissionException $e) {
            $this->assertTrue($e->unknown);
            $this->assertTrue($e->retryable);
        }
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

    /**
     * An expired certificate, a stale token, or a mistyped coupon path makes ATK
     * refuse the request without ever judging the coupon. Treating that as a
     * permanent rejection destroys a valid receipt over a device fault the
     * operator can fix, so these stay retryable and delivery resumes once the
     * device is put right.
     */
    #[DataProvider('deviceFaultStatuses')]
    public function test_a_device_fault_does_not_reject_the_coupon(int $status): void
    {
        $client = $this->clientWithResponse(new Response($status, [], '{"message":"denied"}'));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail("Expected HTTP {$status} to raise a submission exception.");
        } catch (FiscalSubmissionException $e) {
            $this->assertTrue($e->retryable, "HTTP {$status} is a device fault, not a coupon verdict.");
            $this->assertFalse($e->unknown, "HTTP {$status} never reached a verdict, but it is not an unknown result.");
        }
    }

    public static function deviceFaultStatuses(): array
    {
        return [
            'unauthorized'    => [401],
            'forbidden'       => [403],
            'not found'       => [404],
            'method mismatch' => [405],
            'proxy auth'      => [407],
        ];
    }

    /**
     * A verdict on the payload itself is permanent: resubmitting the same signed
     * bytes earns the same answer.
     */
    #[DataProvider('verdictStatuses')]
    public function test_a_payload_verdict_is_permanent(int $status): void
    {
        $client = $this->clientWithResponse(new Response($status, [], '{"message":"invalid coupon"}'));

        try {
            $client->submit(new SignedPayload('details', 'signature'), $this->config());
            $this->fail("Expected HTTP {$status} to raise a submission exception.");
        } catch (FiscalSubmissionException $e) {
            $this->assertFalse($e->retryable, "HTTP {$status} is a verdict on the payload.");
        }
    }

    public static function verdictStatuses(): array
    {
        return ['bad request' => [400], 'conflict' => [409], 'unprocessable' => [422]];
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
