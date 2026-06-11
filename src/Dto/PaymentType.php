<?php

namespace Jointdots\FiskalizimiKs\Dto;

enum PaymentType: string
{
    case Cash           = 'cash';
    case CreditCard     = 'credit_card';
    case Voucher        = 'voucher';
    case Cheque         = 'cheque';
    case CryptoCurrency = 'cryptocurrency';
    case Other          = 'other';
}
