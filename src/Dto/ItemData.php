<?php

namespace Jointdots\FiskalizimiKs\Dto;

final class ItemData
{
    public function __construct(
        public readonly string $name,
        public readonly int    $price,       // item price units (x 10000 per EUR)
        public readonly string $unit,
        public readonly float  $quantity,
        public readonly int    $total,       // item price units (x 10000 per EUR, gross after discount)
        public readonly string $taxRate,     // 'C', 'D', or 'E'
        public readonly string $type = 'TT',
    ) {}
}
