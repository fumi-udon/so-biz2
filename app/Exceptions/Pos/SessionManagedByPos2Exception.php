<?php

declare(strict_types=1);

namespace App\Exceptions\Pos;

use Exception;

/**
 * 当該卓セッションが POS V2 で開局・管理されており、旧 POS からの操作を拒否するとき。
 */
final class SessionManagedByPos2Exception extends Exception
{
    public static function forSession(int $sessionId): self
    {
        return new self(__('pos.session_managed_by_pos2', ['id' => $sessionId]));
    }
}
