<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Card = 'card';
    case Voucher = 'voucher';
    case BypassForced = 'bypass_forced';
}
