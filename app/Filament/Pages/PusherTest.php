<?php

namespace App\Filament\Pages;

use App\Events\Kds\OrderConfirmedBroadcast;
use App\Filament\Support\AdminOnlyPage;
use App\Models\Shop;
use Filament\Notifications\Notification;
use Throwable;

class PusherTest extends AdminOnlyPage
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'debug/pusher-test';

    protected static ?string $title = 'Pusher Test';

    protected static string $view = 'filament.pages.pusher-test';

    public int $shopId = 0;

    public string $shopName = '';

    public ?string $lastDispatchAt = null;

    public function mount(): void
    {
        $shop = Shop::query()->where('is_active', true)->orderBy('id')->first();
        $this->shopId = (int) ($shop?->id ?? 0);
        $this->shopName = (string) ($shop?->name ?? '');
    }

    public function pingPusher(): void
    {
        if ($this->shopId < 1) {
            Notification::make()
                ->title('No active shop')
                ->warning()
                ->send();

            return;
        }

        try {
            broadcast(new OrderConfirmedBroadcast(shopId: $this->shopId));
            $this->lastDispatchAt = now()->format('H:i:s');

            Notification::make()
                ->title('Ping dispatched')
                ->body('Shop #'.$this->shopId.' @ '.$this->lastDispatchAt)
                ->success()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Ping failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
