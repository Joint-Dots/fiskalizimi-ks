<?php

namespace Jointdots\FiskalizimiKs;

use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\FiscalResult;
use Jointdots\FiskalizimiKs\Dto\FiscalStatus;
use Jointdots\FiskalizimiKs\Engine\AtkClientInterface;
use Jointdots\FiskalizimiKs\Engine\CouponBuilder;
use Jointdots\FiskalizimiKs\Engine\CouponSnapshot;
use Jointdots\FiskalizimiKs\Engine\QrGenerator;
use Jointdots\FiskalizimiKs\Engine\SignerInterface;
use Jointdots\FiskalizimiKs\Exceptions\FiscalConfigurationException;
use Jointdots\FiskalizimiKs\Exceptions\FiscalSubmissionException;
use Jointdots\FiskalizimiKs\Jobs\ResubmitCouponJob;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;
use Throwable;

final class FiskalizimiService
{
    public function __construct(
        private readonly CouponBuilder       $builder,
        private readonly SignerInterface     $signer,
        private readonly QrGenerator         $qrGenerator,
        private readonly AtkClientInterface  $atkClient,
    ) {}

    public function fiscalize(CouponData $data, FiscalConfig $config): FiscalResult
    {
        if ($data->idempotencyKey !== null) {
            if ($data->idempotencyKey === '' || strlen($data->idempotencyKey) > 64) {
                throw new FiscalConfigurationException(
                    'Idempotency key must contain between 1 and 64 characters.'
                );
            }
        }

        if ($data->idempotencyKey !== null) {
            $existing = FiscalCoupon::query()
                ->where('idempotency_key', $data->idempotencyKey)
                ->whereNotIn('fiscal_status', [FiscalCoupon::STATUS_REJECTED])
                ->first();

            if ($existing !== null) {
                return $this->resultFrom($existing);
            }
        }

        $snapshot = CouponSnapshot::generate(
            existsChecker: fn(string $no) => FiscalCoupon::query()
                ->where('fiscal_verification_no', $no)
                ->exists(),
            verificationNo: $data->verificationNo,
            time: $data->fiscalTime,
        );

        $journalAttributes = [
            'fiscal_status'          => FiscalCoupon::STATUS_SUBMITTING,
            'fiscal_verification_no' => $snapshot->verificationNo,
            'fiscal_time'            => $snapshot->time,
            'fiscal_error'           => null,
            'fiscal_details'         => null,
            'fiscal_signature'       => null,
            'fiscal_citizen_qr'      => null,
            'atk_transaction_no'     => null,
            'fiscalized_at'          => null,
        ];

        if ($data->idempotencyKey !== null) {
            $journal = FiscalCoupon::query()->firstOrCreate(
                ['idempotency_key' => $data->idempotencyKey],
                $journalAttributes,
            );

            if (!$journal->wasRecentlyCreated) {
                if ($journal->fiscal_status !== FiscalCoupon::STATUS_REJECTED) {
                    return $this->resultFrom($journal);
                }

                $claimed = FiscalCoupon::query()
                    ->whereKey($journal->id)
                    ->where('fiscal_status', FiscalCoupon::STATUS_REJECTED)
                    ->update($journalAttributes);

                if ($claimed !== 1) {
                    return $this->resultFrom($journal->fresh());
                }

                $journal->refresh();
            }
        } else {
            $journal = FiscalCoupon::query()->create($journalAttributes);
        }

        $couponId = $data->couponId ?? (int) $journal->id;
        try {
            $built = $this->builder->build($snapshot, $data, $config, $couponId);
            $signedPayload = $this->signer->sign($built->posCoupon, $config);
            $citizenQr = $this->qrGenerator->generate($built->citizenCoupon, $config);
        } catch (Throwable $e) {
            $journal->update([
                'fiscal_status' => FiscalCoupon::STATUS_FAILED,
                'fiscal_error'  => $e->getMessage(),
            ]);

            throw $e;
        }

        $journal->update([
            'fiscal_details'    => $signedPayload->details,
            'fiscal_signature'  => $signedPayload->signature,
            'fiscal_citizen_qr' => $citizenQr,
        ]);

        try {
            $transactionNo = $this->atkClient->submit($signedPayload, $config);

            $journal->update([
                'fiscal_status'      => FiscalCoupon::STATUS_FISCALIZED,
                'atk_transaction_no' => $transactionNo,
                'fiscalized_at'      => now(),
            ]);

            return new FiscalResult(
                journalId:      $journal->id,
                verificationNo: $snapshot->verificationNo,
                citizenQr:      $citizenQr,
                fiscalTime:     $snapshot->time,
                transactionNo:  $transactionNo,
                status:         FiscalStatus::Fiscalized,
            );
        } catch (FiscalSubmissionException $e) {
            if (!$e->retryable) {
                $journal->update([
                    'fiscal_status' => FiscalCoupon::STATUS_REJECTED,
                    'fiscal_error'  => $e->getMessage(),
                ]);

                return new FiscalResult(
                    journalId:      $journal->id,
                    verificationNo: $snapshot->verificationNo,
                    citizenQr:      $citizenQr,
                    fiscalTime:     $snapshot->time,
                    transactionNo:  null,
                    status:         FiscalStatus::Rejected,
                    error:          $e->getMessage(),
                );
            }

            $journal->update([
                'fiscal_status' => FiscalCoupon::STATUS_QUEUED,
                'fiscal_error'  => $e->getMessage(),
            ]);

            if (config('fiskalizimi.retry.auto_dispatch', true)) {
                ResubmitCouponJob::dispatch($journal->id)->delay(now()->addSeconds(30));
            }

            return new FiscalResult(
                journalId:      $journal->id,
                verificationNo: $snapshot->verificationNo,
                citizenQr:      $citizenQr,
                fiscalTime:     $snapshot->time,
                transactionNo:  null,
                status:         FiscalStatus::Queued,
            );
        }
    }

    private function resultFrom(FiscalCoupon $coupon): FiscalResult
    {
        return new FiscalResult(
            journalId:      $coupon->id,
            verificationNo: (string) $coupon->fiscal_verification_no,
            citizenQr:      (string) $coupon->fiscal_citizen_qr,
            fiscalTime:     (int) $coupon->fiscal_time,
            transactionNo:  $coupon->atk_transaction_no ? (string) $coupon->atk_transaction_no : null,
            status:         FiscalStatus::from((string) $coupon->fiscal_status),
            error:          $coupon->fiscal_error ?: null,
        );
    }
}
