<?php

namespace App\Livewire\Pos;

use Livewire\Component;

/**
 * ClotureModal を pos-cloture-open で自動オープンするブリッジ用ラッパー。
 */
class Pos2BridgeClotureHost extends Component
{
    public int $shopId = 0;

    public int $tableSessionId = 0;

    public int $expectedSessionRevision = 0;

    public function mount(int $shopId, int $tableSessionId, int $expectedSessionRevision): void
    {
        $this->shopId = $shopId;
        $this->tableSessionId = $tableSessionId;
        $this->expectedSessionRevision = $expectedSessionRevision;
    }

    public function openClotureModal(): void
    {
        $this->dispatch(
            'pos-cloture-open',
            shop_id: $this->shopId,
            table_session_id: $this->tableSessionId,
            expected_revision: $this->expectedSessionRevision,
            settlement_initiator: 'pos2_bridge',
        );
    }

    public function render()
    {
        return view('livewire.pos.pos2-bridge-cloture-host');
    }
}
