<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminOnlyPage;
use App\Models\Order;
use Livewire\Attributes\On;

class OrderMonitor extends AdminOnlyPage
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = '受注モニター';

    protected static string $view = 'filament.pages.order-monitor';

    protected static ?string $navigationLabel = '受注モニター';

    protected static ?string $title = '受注モニター';

    public ?string $blinkingTable = null;

    /**
     * テーブル番号（キー）ごとの未処理注文（配列の配列）。
     *
     * @var array<string, list<array{id: int, items: string, created_at: string|null}>>
     */
    public array $ordersByTable = [];

    public function mount(): void
    {
        $this->loadOrders();
    }

    #[On('echo:orders,.OrderPlaced')]
    public function onOrderPlaced(mixed $payload = null): void
    {
        $this->loadOrders();

        $table = null;

        if (is_array($payload)) {
            $table = $payload['table_number'] ?? null;
        }

        if ($table !== null && $table !== '') {
            $this->blinkingTable = (string) $table;
            $this->js('setTimeout(() => $wire.clearBlink(), 2500)');
        }
    }

    public function clearBlink(): void
    {
        $this->blinkingTable = null;
    }

    public function loadOrders(): void
    {
        $pending = Order::query()
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->get();

        foreach (config('restaurant.tables', ['1', '2', '3', '4']) as $t) {
            $t = (string) $t;
            $this->ordersByTable[$t] = $pending
                ->filter(fn (Order $o): bool => (string) $o->table_number === $t)
                ->values()
                ->map(fn (Order $o): array => [
                    'id' => $o->id,
                    'items' => $o->items,
                    'created_at' => $o->created_at?->format('Y-m-d H:i'),
                ])
                ->all();
        }
    }
}
