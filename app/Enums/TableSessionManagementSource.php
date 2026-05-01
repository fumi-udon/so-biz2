<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * 卓セッションの主たる操作クライアント（旧 Livewire POS と POS V2 の並行運用境界）。
 */
enum TableSessionManagementSource: string
{
    case Legacy = 'legacy';
    case Pos2 = 'pos2';
}
