<?php

namespace App\Livewire\Pos;

use App\Models\Shop;
use App\Models\TableSessionSettlement;
use App\Support\MenuItemMoney;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Component;

class ClotureHistoryPage extends Component
{
    public int $shopId = 0;

    public string $shopName = '';

    public bool $detailOpen = false;

    public ?int $selectedSettlementId = null;

    public string $selectedReceiptUrl = '';

    public function mount(): void
    {
        $this->shopId = $this->resolveCurrentShopId();
        $this->shopName = $this->shopId > 0
            ? (string) (Shop::query()->whereKey($this->shopId)->value('name') ?? '')
            : '';
    }

    public function openDetail(int $settlementId): void
    {
        /** @var TableSessionSettlement|null $settlement */
        $settlement = TableSessionSettlement::query()
            ->whereKey($settlementId)
            ->where('shop_id', $this->shopId)
            ->first();

        if ($settlement === null) {
            return;
        }

        $expectedRevision = max(0, (int) ($settlement->session_revision_at_settle ?? 0));
        $this->selectedSettlementId = (int) $settlement->id;
        $this->selectedReceiptUrl = route('pos.receipt-preview.page', [
            'shop_id' => (int) $settlement->shop_id,
            'table_session_id' => (int) $settlement->table_session_id,
            'expected_revision' => $expectedRevision,
            'intent' => 'copy',
        ]);
        $this->detailOpen = true;
    }

    public function closeDetail(): void
    {
        $this->detailOpen = false;
    }

    public function getHistoryRowsProperty(): Collection
    {
        if ($this->shopId < 1) {
            return collect();
        }

        [$startAt, $endAt] = $this->businessDayRange();

        return TableSessionSettlement::query()
            ->where('shop_id', $this->shopId)
            ->whereBetween('settled_at', [$startAt, $endAt])
            ->with([
                'tableSession.restaurantTable:id,name',
                'settledBy:id,name',
            ])
            ->orderByDesc('settled_at')
            ->orderByDesc('id')
            ->get();
    }

    public function formatMinor(int $minor): string
    {
        return MenuItemMoney::formatMinorForDisplay($minor);
    }

    public function paymentLabel(?string $method): string
    {
        return match ($method) {
            'cash' => __('rad_table.cloture_payment_cash'),
            'card' => __('rad_table.cloture_payment_card'),
            'voucher' => __('rad_table.cloture_payment_voucher'),
            'bypass_forced' => 'Bypass',
            default => '—',
        };
    }

    public function render()
    {
        return view('livewire.pos.cloture-history-page');
    }

    /**
     * 営業日境界: 04:00:00 起点（04:00〜翌03:59:59）。
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function businessDayRange(): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $now = Carbon::now($tz);
        $businessStart = $now->copy()->setTime(4, 0, 0);

        if ($now->lt($businessStart)) {
            $businessStart->subDay();
        }

        $businessEnd = $businessStart->copy()->addDay()->subSecond();

        return [$businessStart, $businessEnd];
    }

    private function resolveCurrentShopId(): int
    {
        $preferredShopId = (int) config('pos.default_shop_id', 3);
        $shop = null;

        if ($preferredShopId > 0) {
            $shop = Shop::query()
                ->whereKey($preferredShopId)
                ->where('is_active', true)
                ->first();
        }

        if ($shop === null) {
            $shop = Shop::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();
        }

        return (int) ($shop?->id ?? 0);
    }
}
