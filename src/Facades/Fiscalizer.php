<?php

namespace Jointdots\FiskalizimiKs\Facades;

use Illuminate\Support\Facades\Facade;
use Jointdots\FiskalizimiKs\FiskalizimiService;

/**
 * @method static \Jointdots\FiskalizimiKs\Dto\FiscalResult fiscalize(\Jointdots\FiskalizimiKs\Dto\CouponData $data, \Jointdots\FiskalizimiKs\Dto\FiscalConfig $config)
 */
class Fiscalizer extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FiskalizimiService::class;
    }
}
