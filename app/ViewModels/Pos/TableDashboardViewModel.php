<?php

namespace App\ViewModels\Pos;

use App\Data\Pos\TableDashboardData;

/**
 * V4: docs/technical_contract_v4.md §2.1
 *
 * 入力は {@see TableDashboardData} のみ。本クラス内での DB 再クエリは禁止。
 */
final class TableDashboardViewModel
{
    public function __construct(
        private readonly TableDashboardData $data,
    ) {}

    public function data(): TableDashboardData
    {
        return $this->data;
    }
}
