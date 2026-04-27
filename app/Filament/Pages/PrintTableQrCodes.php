<?php

namespace App\Filament\Pages;

use App\Models\RestaurantTable;
use App\Models\Shop;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;

class PrintTableQrCodes extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-qr-code';

    protected static string $view = 'filament.pages.print-table-qr-codes';

    protected static ?string $navigationLabel = 'QRコード印刷 / Print QR Codes';

    protected static ?string $navigationGroup = 'Menu & orders';

    protected static ?int $navigationSort = 17;

    public int $shopId = 0;

    public string $shopName = '';

    public string $shopSlug = '';

    /**
     * @var Collection<int, RestaurantTable>
     */
    public Collection $tables;

    public function mount(): void
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
            $shop = Shop::query()->where('is_active', true)->orderBy('id')->first();
        }

        $this->shopId = (int) ($shop?->id ?? 0);
        $this->shopName = (string) ($shop?->name ?? '');
        $this->shopSlug = (string) ($shop?->slug ?? '');

        $this->tables = $this->shopId > 0
            ? RestaurantTable::query()
                ->where('shop_id', $this->shopId)
                ->where('is_active', true)
                ->whereNotNull('qr_token')
                ->where('qr_token', '!=', '')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
            : collect();
    }

    public function getTitle(): string|Htmlable
    {
        return 'QRコード印刷 / Print QR Codes';
    }

    public function getMaxContentWidth(): ?MaxWidth
    {
        return MaxWidth::Full;
    }

    /**
     * Full guest URL for QR payload (api.qrserver.com `data` param).
     */
    public function guestUrlForTable(RestaurantTable $table): string
    {
        if ($this->shopSlug === '' || trim((string) $table->qr_token) === '') {
            return '';
        }

        return route('guest.menu', [
            'tenantSlug' => $this->shopSlug,
            'tableToken' => (string) $table->qr_token,
        ], absolute: true);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        $parent = parent::getExtraBodyAttributes();
        $class = trim(((string) ($parent['class'] ?? '')).' fi-print-table-qr-page');

        return array_merge($parent, [
            'class' => $class,
        ]);
    }
}
