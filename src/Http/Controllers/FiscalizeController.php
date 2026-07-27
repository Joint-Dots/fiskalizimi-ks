<?php

namespace Jointdots\FiskalizimiKs\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Jointdots\FiskalizimiKs\Dto\CouponType;
use Jointdots\FiskalizimiKs\Dto\FiscalConfig;
use Jointdots\FiskalizimiKs\Dto\FiscalResult;
use Jointdots\FiskalizimiKs\Dto\FiscalStatus;
use Jointdots\FiskalizimiKs\FiskalizimiService;
use Jointdots\FiskalizimiKs\Http\Requests\FiscalizeRequest;
use Jointdots\FiskalizimiKs\Models\FiscalCoupon;

class FiscalizeController extends Controller
{
    public function __construct(
        private readonly FiskalizimiService $service,
        private readonly FiscalConfig       $config,
    ) {}

    public function fiscalize(FiscalizeRequest $request): JsonResponse
    {
        $result = $this->service->fiscalize($request->toCouponData(CouponType::Sale), $this->config);

        return response()->json($this->resultJson($result), $this->statusCode($result->status));
    }

    public function fiscalizeReturn(FiscalizeRequest $request): JsonResponse
    {
        $request->validate(['reference_no' => ['required', 'integer', 'min:1']]);
        $result = $this->service->fiscalize($request->toCouponData(CouponType::Return), $this->config);

        return response()->json($this->resultJson($result), $this->statusCode($result->status));
    }

    public function fiscalizeCancel(FiscalizeRequest $request): JsonResponse
    {
        $request->validate(['reference_no' => ['required', 'integer', 'min:1']]);
        $result = $this->service->fiscalize($request->toCouponData(CouponType::Cancel), $this->config);

        return response()->json($this->resultJson($result), $this->statusCode($result->status));
    }

    public function status(int $id): JsonResponse
    {
        $coupon = FiscalCoupon::findOrFail($id);

        return response()->json([
            'id'              => $coupon->id,
            'status'          => $coupon->fiscal_status,
            'verification_no' => $coupon->fiscal_verification_no,
            'citizen_qr'      => $coupon->fiscal_citizen_qr,
            'fiscal_time'     => $coupon->fiscal_time,
            'transaction_no'  => $coupon->atk_transaction_no,
        ]);
    }

    private function resultJson(FiscalResult $r): array
    {
        return [
            'id'              => $r->journalId,
            'status'          => $r->status->value,
            'verification_no' => $r->verificationNo,
            'citizen_qr'      => $r->citizenQr,
            'fiscal_time'     => $r->fiscalTime,
            'transaction_no'  => $r->transactionNo,
            'error'           => $r->error,
        ];
    }

    private function statusCode(FiscalStatus $status): int
    {
        return match ($status) {
            FiscalStatus::Fiscalized => 200,
            FiscalStatus::Queued,
            FiscalStatus::Pending,
            FiscalStatus::Submitting => 202,
            FiscalStatus::Rejected => 422,
            // Sent, but ATK's answer could not be read and retrying did not clear
            // it. Neither accepted nor refused nor progressing: 202 would tell the
            // caller to wait for a resolution that will never arrive on its own.
            FiscalStatus::Unresolved => 409,
            FiscalStatus::Failed => 500,
        };
    }
}
