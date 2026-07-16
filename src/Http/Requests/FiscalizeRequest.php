<?php

namespace Jointdots\FiskalizimiKs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Jointdots\FiskalizimiKs\Dto\CouponData;
use Jointdots\FiskalizimiKs\Dto\CouponType;
use Jointdots\FiskalizimiKs\Dto\ItemData;
use Jointdots\FiskalizimiKs\Dto\PaymentData;
use Jointdots\FiskalizimiKs\Dto\PaymentType;
use Jointdots\FiskalizimiKs\Engine\VerificationNo;

class FiscalizeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'idempotency_key'         => ['required', 'string', 'max:64'],
            'verification_no'         => ['sometimes', 'nullable', 'string', 'regex:' . VerificationNo::PATTERN],
            'operator_id'             => ['required', 'string', 'max:120'],
            'items'                   => ['required', 'array', 'min:1'],
            'items.*.name'            => ['required', 'string', 'max:120'],
            'items.*.price'           => ['required', 'integer', 'min:0'],
            'items.*.unit'            => ['required', 'string', 'max:20'],
            'items.*.quantity'        => ['required', 'numeric', 'min:0.001'],
            'items.*.total'           => ['required', 'integer', 'min:0'],
            'items.*.tax_rate'        => ['required', 'string', 'in:A,C,D,E'],
            'items.*.type'            => ['sometimes', 'string', 'max:10'],
            'payments'                => ['required', 'array', 'min:1'],
            'payments.*.type'         => ['required', 'string', 'in:cash,credit_card,voucher,cheque,cryptocurrency,other'],
            'payments.*.amount'       => ['required', 'integer', 'min:1'],
            'total'                   => ['required', 'integer', 'min:1'],
            'total_discount'          => ['sometimes', 'integer', 'min:0', 'lte:total'],
            'reference_no'            => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $itemTotal = array_sum(array_column((array) $this->input('items', []), 'total'));
            $paymentTotal = array_sum(array_column((array) $this->input('payments', []), 'amount'));
            $declaredTotal = (int) $this->input('total', 0);

            if ($declaredTotal !== (int) $itemTotal) {
                $validator->errors()->add('total', 'The declared total must equal the sum of item totals.');
            }

            if ($declaredTotal !== (int) $paymentTotal) {
                $validator->errors()->add('payments', 'The payment total must equal the declared total.');
            }
        });
    }

    public function toCouponData(CouponType $type = CouponType::Sale): CouponData
    {
        $items = array_map(fn(array $i) => new ItemData(
            name:     $i['name'],
            price:    (int) $i['price'],
            unit:     $i['unit'],
            quantity: (float) $i['quantity'],
            total:    (int) $i['total'],
            taxRate:  $i['tax_rate'],
            type:     $i['type'] ?? 'TT',
        ), $this->input('items'));

        $payments = array_map(fn(array $p) => new PaymentData(
            type:   PaymentType::from($p['type']),
            amount: (int) $p['amount'],
        ), $this->input('payments'));

        return new CouponData(
            items:          $items,
            payments:       $payments,
            operatorId:     $this->input('operator_id'),
            type:           $type,
            referenceNo:    $this->input('reference_no'),
            idempotencyKey: $this->input('idempotency_key'),
            verificationNo: $this->input('verification_no'),
            totalDiscount:  (int) $this->input('total_discount', 0),
        );
    }
}
