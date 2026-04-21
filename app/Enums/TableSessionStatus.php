<?php

namespace App\Enums;

enum TableSessionStatus: string
{
    case Active = 'active';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
