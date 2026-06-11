<?php

use Illuminate\Support\Facades\Route;
use Jointdots\FiskalizimiKs\Http\Controllers\FiscalizeController;
use Jointdots\FiskalizimiKs\Http\Middleware\FiscalApiTokenMiddleware;
use Jointdots\FiskalizimiKs\Http\Middleware\LogFiscalRequestMiddleware;

$prefix = config('fiskalizimi.api.prefix', 'api/fiscal');

Route::prefix($prefix)
    ->middleware(['api', FiscalApiTokenMiddleware::class, LogFiscalRequestMiddleware::class])
    ->group(function () {
        Route::post('coupons',        [FiscalizeController::class, 'fiscalize']);
        Route::post('coupons/return', [FiscalizeController::class, 'fiscalizeReturn']);
        Route::post('coupons/cancel', [FiscalizeController::class, 'fiscalizeCancel']);
        Route::get('coupons/{id}',    [FiscalizeController::class, 'status']);
    });
