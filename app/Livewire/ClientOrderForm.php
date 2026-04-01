<?php

namespace App\Livewire;

use App\Events\OrderPlaced;
use App\Models\Order;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.client-order')]
class ClientOrderForm extends Component
{
    public string $table_number = '';

    public string $items = '';

    public bool $submitted = false;

    public function mount(string $table_number): void
    {
        $this->table_number = $table_number;
    }

    public function submit(): void
    {
        $allowedTables = array_map(
            static fn (string $t): int => (int) $t,
            config('restaurant.tables', ['1', '2', '3', '4'])
        );

        $validated = $this->validate([
            'table_number' => ['required', 'integer', Rule::in($allowedTables)],
            'items' => ['required', 'string', 'max:10000'],
        ], attributes: [
            'table_number' => 'テーブル番号',
            'items' => '注文内容',
        ]);

        $order = Order::query()->create([
            'table_number' => (string) $validated['table_number'],
            'items' => $validated['items'],
            'status' => 'pending',
        ]);

        try {
            broadcast(new OrderPlaced($order))->toOthers();
        } catch (\Throwable) {
            // Pusher / ブロードキャスト失敗時も注文保存は維持する
        }

        $this->submitted = true;
        $this->items = '';
    }

    public function render(): View
    {
        return view('livewire.client-order-form');
    }
}
