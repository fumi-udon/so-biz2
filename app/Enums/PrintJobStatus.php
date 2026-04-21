<?php

namespace App\Enums;

enum PrintJobStatus: string
{
    case Pending = 'pending';
    case Dispatched = 'dispatched';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
    case Bypassed = 'bypassed';

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Succeeded, self::Failed, self::Bypassed => true,
            default => false,
        };
    }
}
