<?php

namespace App\Enums;

enum PrintIntent: string
{
    case Addition = 'addition';
    case Receipt = 'receipt';
    case Copy = 'copy';
    case StaffCopy = 'staff_copy';
}
