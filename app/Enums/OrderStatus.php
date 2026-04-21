<?php

namespace App\Enums;

enum OrderStatus: string
{
    case Draft = 'draft';
    case Placed = 'placed';
    case Confirmed = 'confirmed';
    case Paid = 'paid';
    case Voided = 'voided';
}
