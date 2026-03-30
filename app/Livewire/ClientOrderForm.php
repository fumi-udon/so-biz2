<?php

namespace App\Livewire;

use App\Events\OrderPlaced;
use App\Models\Order;
use Illuminate\Contracts\View\View;
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
        $this->validate([
            'items' => ['required', 'string', 'max:10000'],
        ]);

        $order = Order::query()->create([
            'table_number' => $this->table_number,
            'items' => $this->items,
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
