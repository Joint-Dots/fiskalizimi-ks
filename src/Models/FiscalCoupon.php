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

    protected $guarded = [];

    protected $casts = [
        'fiscal_response'    => 'array',
        'fiscal_time'        => 'integer',
        'atk_transaction_no' => 'integer',
        'fiscalized_at'      => 'datetime',
    ];

    public function getTable(): string
    {
        return config('fiskalizimi.table', 'kuponat_fiskal');
    }
}
