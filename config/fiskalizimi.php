<?php

return [
    'table' => env('FISCAL_TABLE', 'kuponat_fiskal'),

    'api' => [
        'enabled'      => env('FISCAL_API_ENABLED', false),
        'token'        => env('FISCAL_API_TOKEN'),
        'prefix'       => env('FISCAL_API_PREFIX', 'api/fiscal'),
        'log_requests' => env('FISCAL_API_LOG_REQUESTS', false),
    ],

    'atk' => [
        'base_url'    => env('FISCAL_ATK_BASE_URL', 'https://fiskalizimi.atk-ks.org'),
        'coupon_path' => env('FISCAL_ATK_COUPON_PATH', '/pos/coupon'),
        'timeout'     => (int) env('FISCAL_ATK_TIMEOUT', 10),
    ],

    'business' => [
        'id'             => (int) env('FISCAL_BUSINESS_ID', 0),
        'application_id' => (int) env('FISCAL_APPLICATION_ID', 0),
        'pos_id'         => (int) env('FISCAL_POS_ID', 0),
        'branch_id'      => (int) env('FISCAL_BRANCH_ID', 0),
        'location'       => env('FISCAL_LOCATION', ''),
        'key_path'       => env('FISCAL_KEY_PATH', ''),
        'key_passphrase' => env('FISCAL_KEY_PASSPHRASE'),
    ],

    'retry' => [
        'auto_dispatch' => env('FISCAL_RETRY_AUTO_DISPATCH', true),
    ],
];
