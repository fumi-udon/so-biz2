<?php

namespace App\Enums;

enum OrderLineStatus: string
{
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Cooking = 'cooking';
    case Served = 'served';
    case Cancelled = 'cancelled';
}
