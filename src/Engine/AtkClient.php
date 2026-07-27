<?php

namespace Jointdots\FiskalizimiKs\Engine;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;

final class AtkClient implements AtkClientInterface
{
    /** Cap on establishing the connection, as opposed to the whole exchange. */
    private const CONNECT_TIMEOUT_SECONDS = 5;

    /** Transport-level congestion: the same bytes will be accepted later. */
    private const TRANSIENT_STATUSES = [408, 425, 429];

    /**
     * The request was refused before the coupon was ever judged — an expired
     * certificate, a stale token, a wrong coupon path. Rejecting the coupon over
     * one of these would destroy a valid receipt over a fault the operator can
     * fix, so they stay retryable and delivery resumes once the device is right.
     */
    private const DEVICE_FAULT_STATUSES = [401, 403, 404, 405, 407];

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
                'json'            => $payload->toRequestPayload(),
                'timeout'         => $config->atkTimeout,
                // Without its own budget, a black-holed connect consumes the whole
                // request timeout before the queue learns the device is offline.
                'connect_timeout' => min(self::CONNECT_TIMEOUT_SECONDS, $config->atkTimeout),
                'http_errors'     => false,
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

            $isDeviceFault = in_array($statusCode, self::DEVICE_FAULT_STATUSES, true);
            $retryable = $statusCode >= 500
                || in_array($statusCode, self::TRANSIENT_STATUSES, true)
                || $isDeviceFault;

            throw new FiscalSubmissionException(
                $isDeviceFault
                    ? "ATK refused the request (HTTP {$statusCode}): {$message}. This is a device or "
                      . 'configuration fault, not a verdict on the coupon.'
                    : "ATK rejected submission (HTTP {$statusCode}): {$message}",
                retryable: $retryable,
            );
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
