<?php

namespace App\Filament\Pages;

use App\Models\Shop;
use App\Support\Pos\PosPrinterClientConfig;
use Filament\Notifications\Notification;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Support\Facades\DB;
use Throwable;

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
                'connect_timeout_ms' => (int) config('pos.printer.connect_timeout_ms', 20_000),
                'idle_disconnect_ms' => (int) config('pos.printer.idle_disconnect_ms', 60_000),
                'device_in_use_retry_max' => (int) config('pos.printer.device_in_use_retry_max', 5),
                'device_in_use_retry_delay_ms' => (int) config('pos.printer.device_in_use_retry_delay_ms', 3_000),
                'buffer' => (bool) config('pos.printer.buffer', false),
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

    public function getCanUseDevPosResetProperty(): bool
    {
        return app()->environment('local') && (bool) config('app.debug');
    }

    public function resetAllPosData(): void
    {
        if (! $this->canUseDevPosReset) {
            abort(403);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $tables = array_map(
                static fn ($t): string => (string) current((array) $t),
                DB::select('SHOW TABLES')
            );

            $targets = ['order_lines', 'orders', 'receipt_lines', 'receipts', 'table_sessions'];
            foreach ($tables as $table) {
                foreach ($targets as $target) {
                    if (str_ends_with($table, $target)) {
                        DB::statement("TRUNCATE TABLE `{$table}`");
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            report($e);

            Notification::make()
                ->title(__('pos.dev_reset_failed'))
                ->danger()
                ->send();

            return;
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->dispatch('pos-refresh-tiles');

        Notification::make()
            ->title(__('pos.dev_reset_done'))
            ->success()
            ->send();
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
