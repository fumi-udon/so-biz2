<?php

namespace App\Domains\Pos\Tables;

enum TableUiStatus: string
{
    case Free = 'free';
    case Pending = 'pending';
    case Active = 'active';
    case Billed = 'billed';
    case Alert = 'alert';
}
