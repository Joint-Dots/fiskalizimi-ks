<?php

namespace Jointdots\FiskalizimiKs\Jobs;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Engine\AtkClientInterface;
use Jointdots\FiskalizimiKs\Engine\SignedPayload;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;
use Throwable;

class ResubmitCouponJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 0;
    public int $timeout = 30;

    public readonly int $retryUntilTimestamp;

    public function __construct(
        public readonly int $couponId,
        ?int $retryUntilTimestamp = null,
    ) {
        $this->retryUntilTimestamp = $retryUntilTimestamp ?? time() + (48 * 60 * 60);
    }

    public function handle(AtkClientInterface $client): void
    {
        $coupon = FiscalCoupon::find($this->couponId);

        if (!$coupon || $coupon->fiscal_status !== FiscalCoupon::STATUS_QUEUED) {
            return;
        }

        $config = app(FiscalConfig::class);

        try {
            $payload = new SignedPayload(
                details:   (string) $coupon->fiscal_details,
                signature: (string) $coupon->fiscal_signature,
            );

            $transactionNo = $client->submit($payload, $config);

            $coupon->update([
                'fiscal_status'      => FiscalCoupon::STATUS_FISCALIZED,
                'atk_transaction_no' => $transactionNo,
                'fiscal_error'       => null,
                'fiscalized_at'      => now(),
            ]);
        } catch (FiscalSubmissionException $e) {
            $coupon->update(['fiscal_error' => $e->getMessage()]);

            if (!$e->retryable) {
                $coupon->update(['fiscal_status' => FiscalCoupon::STATUS_REJECTED]);
                return;
            }

            $this->release($this->nextBackoff());
        }
    }

    public function retryUntil(): DateTimeInterface
    {
        return (new DateTimeImmutable())->setTimestamp($this->retryUntilTimestamp);
    }

    public function backoff(): array
    {
        return [30, 60, 120, 300, 600, 1200, 1800, 3600, 3600, 3600];
    }

    private function nextBackoff(): int
    {
        return $this->backoff()[$this->attempts() - 1] ?? 3600;
    }

    public function failed(?Throwable $exception): void
    {
        FiscalCoupon::query()
            ->whereKey($this->couponId)
            ->where('fiscal_status', FiscalCoupon::STATUS_QUEUED)
            ->update([
                'fiscal_status' => FiscalCoupon::STATUS_FAILED,
                'fiscal_error'  => $exception?->getMessage() ?? 'ATK resubmission deadline expired.',
            ]);
    }
}
