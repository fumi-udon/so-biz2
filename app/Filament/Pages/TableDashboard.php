<?php

namespace App\Filament\Pages;

use App\Models\Shop;
use App\Support\Pos\PosPrinterClientConfig;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;

class TableDashboard extends Page
{
    /**
     * サイドバー / トップバーなしの全画面キオスク（A6 タブレット向け）。
     */
    protected static string $layout = 'filament.layouts.pos-kiosk';

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    protected static string $view = 'filament.pages.table-dashboard';

    protected static ?string $navigationLabel = 'Table dashboard / POS';

    protected static ?string $title = '';

    protected static ?string $navigationGroup = 'Menu & orders';

    protected static ?int $navigationSort = 16;

    public int $shopId = 0;

    public string $shopName = '';

    /**
     * Staff meal ghost row (tables 100–104): toggled from the footer "door" control.
     * Livewire SSOT so visibility survives nested component morphs (no Alpine scope bugs).
     */
    public bool $staffDoorOpen = false;

    /**
     * DB (SSOT) printer fields; merged in the kiosk Blade with {@see localStorage} override
     * before assigning {@see window.posPrinterConfig}.
     *
     * @var array<string, mixed>
     */
    public array $posPrinterDeviceDefaults = [];

    public function mount(): void
    {
        $shop = Shop::query()->where('is_active', true)->orderBy('id')->first();
        $this->shopId = $shop?->id ?? 0;
        if ($this->shopId > 0) {
            $this->shopName = (string) (Shop::query()->whereKey($this->shopId)->value('name') ?? '');
        }

        $this->posPrinterDeviceDefaults = $this->shopId > 0
            ? PosPrinterClientConfig::resolveForShopId((int) $this->shopId)
            : [
                'shop_id' => 0,
                'driver' => 'mock',
                'url' => '',
                'timeoutMs' => 10_000,
                'printer_ip' => (string) config('pos_printer.defaults.printer_ip', '192.168.1.200'),
                'printer_port' => (string) config('pos_printer.defaults.printer_port', '8043'),
                'device_id' => (string) config('pos_printer.defaults.device_id', 'local_printer'),
                'crypto' => (bool) config('pos_printer.defaults.crypto', true),
                'timeout_ms' => (int) config('pos_printer.defaults.timeout_ms', 10_000),
            ];
    }

    public function getMaxContentWidth(): ?MaxWidth
    {
        return MaxWidth::Full;
    }

    public function getHeading(): string
    {
        return '';
    }

    /**
     * ブラウザの縦バウンドとページ全体スクロールを抑止（キオスク）。
     *
     * @return array<string, mixed>
     */
    public function getExtraBodyAttributes(): array
    {
        return array_merge(parent::getExtraBodyAttributes(), [
            'class' => 'h-[100dvh] max-h-[100dvh] overflow-hidden overscroll-none',
        ]);
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canAccessPanel(Filament::getCurrentPanel());
    }
}
