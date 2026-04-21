<?php

namespace App\Domains\Pos\Discount;

enum DiscountType: string
{
    case Item = 'item';
    case Order = 'order';
    case Staff = 'staff';
}
