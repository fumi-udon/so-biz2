<?php

namespace App\Domains\Pos\Tables;

use InvalidArgumentException;

enum TableCategory: string
{
    case Customer = 'customer';
    case Staff = 'staff';
    case Takeaway = 'takeaway';

    public static function tryResolveFromId(int $id): ?self
    {
        return match (true) {
            $id >= 10 && $id <= 29 => self::Customer,
            $id >= 100 && $id <= 109 => self::Staff,
            $id >= 200 && $id <= 219 => self::Takeaway,
            default => null,
        };
    }

    public static function resolveFromIdOrFail(int $id): self
    {
        $resolved = self::tryResolveFromId($id);
        if ($resolved !== null) {
            return $resolved;
        }

        throw new InvalidArgumentException('Unknown table category id: '.$id);
    }
}
