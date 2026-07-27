<?php

namespace Jointdots\FiskalizimiKs\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;

final class AtkClient implements AtkClientInterface
{
    private ClientInterface $http;

    public function __construct(?ClientInterface $http = null)
    {
        $this->http = $http ?? new Client();
    }

    public function submit(SignedPayload $payload, FiscalConfig $config): int
    {
        $endpoint = rtrim($config->atkBaseUrl, '/') . '/' . ltrim($config->atkCouponPath, '/');

        try {
            $response = $this->http->request('POST', $endpoint, [
                'json'        => $payload->toRequestPayload(),
                'timeout'     => $config->atkTimeout,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            throw new FiscalSubmissionException('ATK request failed: ' . $e->getMessage(), retryable: true, previous: $e);
        }

        $statusCode = $response->getStatusCode();
        $body       = (string) $response->getBody();
        $decoded    = json_decode($body, true);

        if ($statusCode < 200 || $statusCode >= 300) {
            $message = is_array($decoded)
                ? ($decoded['message'] ?? $decoded['error'] ?? $body)
                : $body;
            $retryable = $statusCode >= 500 || in_array($statusCode, [408, 425, 429], true);
            throw new FiscalSubmissionException("ATK rejected submission (HTTP {$statusCode}): {$message}", retryable: $retryable);
        }

        $transactionNo = is_array($decoded)
            ? ($decoded['transaction_id'] ?? $decoded['transactionNo'] ?? $decoded['transaction_no'] ?? null)
            : null;
        if (!is_numeric($transactionNo)) {
            throw new FiscalSubmissionException(
                "ATK accepted the request (HTTP {$statusCode}) but its response carried no transaction number, "
                . "so the coupon's fate at ATK is unknown. Body: {$body}",
                retryable: true,
                unknown: true,
            );
        }

        return (int) $transactionNo;
    }
}
