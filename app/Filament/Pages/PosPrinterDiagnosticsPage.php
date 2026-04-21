<?php

namespace App\Filament\Pages;

use App\Models\Shop;
use App\Support\Pos\PosPrinterClientConfig;
use Filament\Facades\Filament;
use Filament\Pages\Page;

class PosPrinterDiagnosticsPage extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.pos-printer-diagnostics';

    protected static ?string $navigationLabel = 'Printer diagnostics';

    protected static ?string $title = 'Printer diagnostics';

    protected static ?string $navigationGroup = 'POS Ops';

    protected static ?int $navigationSort = 90;

    public int $shopId = 0;

    public string $shopName = '';

    /**
     * DB-backed defaults (SSOT); merged client-side with {@see localStorage} `pos_printer_override`.
     *
     * @var array<string, mixed>
     */
    public array $dbPrinterDeviceDefaults = [];

    public function mount(): void
    {
        $shop = Shop::query()->where('is_active', true)->orderBy('id')->first();
        $this->shopId = (int) ($shop?->id ?? 0);
        $this->shopName = (string) ($shop?->name ?? '');
        $this->dbPrinterDeviceDefaults = $this->shopId > 0
            ? PosPrinterClientConfig::resolveForShopId($this->shopId)
            : [
                'shop_id' => 0,
                'printer_ip' => (string) config('pos_printer.defaults.printer_ip', '192.168.1.200'),
                'printer_port' => (string) config('pos_printer.defaults.printer_port', '8043'),
                'device_id' => (string) config('pos_printer.defaults.device_id', 'local_printer'),
                'crypto' => (bool) config('pos_printer.defaults.crypto', true),
                'timeout_ms' => (int) config('pos_printer.defaults.timeout_ms', 10_000),
            ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null || ! $user->canAccessPanel(Filament::getCurrentPanel())) {
            return false;
        }

        $superAdmin = config('filament-shield.super_admin.name', 'super_admin');

        return $user->hasRole($superAdmin) || $user->hasRole('manager');
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
