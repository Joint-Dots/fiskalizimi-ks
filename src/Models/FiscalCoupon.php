<?php

namespace Jointdots\FiskalizimiKs\Models;

use Illuminate\Database\Eloquent\Model;

class FiscalCoupon extends Model
{
    const STATUS_PENDING     = 'pending';
    const STATUS_SUBMITTING  = 'submitting';
    const STATUS_FISCALIZED  = 'fiscalized';
    const STATUS_QUEUED      = 'queued';
    const STATUS_FAILED      = 'failed';
    const STATUS_REJECTED    = 'rejected';

    /**
     * ATK received the submission but its outcome could not be read, and retrying
     * did not clear it. The coupon may or may not be recorded at ATK, so it is
     * held for an operator decision rather than resolved by guessing.
     */
    const STATUS_UNRESOLVED  = 'unresolved';

    protected $guarded = [];

    protected $casts = [
        'fiscal_response'    => 'array',
        'fiscal_time'        => 'integer',
        'atk_transaction_no' => 'integer',
        'fiscalized_at'      => 'datetime',
        // When ATK first answered this submission unreadably. Optional: only
        // consumers that retry an unknown result before escalating populate it.
        'unknown_since'      => 'datetime',
    ];

    public function getTable(): string
    {
        return config('fiskalizimi.table', 'kuponat_fiskal');
    }
}
