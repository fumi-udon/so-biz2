<?php

namespace App\Filament\Pages;

use App\Actions\RadTable\CheckoutTableSessionAction;
use App\Actions\RadTable\RecordAdditionPrintForSessionAction;
use App\Actions\RadTable\RecuPlacedOrdersForSessionAction;
use App\Enums\OrderStatus;
use App\Enums\RadTableTileColor;
use App\Models\RestaurantTable;
use App\Models\Shop;
use App\Models\TableSession;
use App\Services\RadTable\RadTableTileResolver;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Throwable;

class RadTableApp extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';

    protected static string $view = 'filament.pages.rad-table-app';

    protected static ?string $navigationLabel = 'ラドテーブル';

    protected static ?string $title = 'ラドテーブル';

    protected static ?string $navigationGroup = 'メニュー・注文';

    protected static ?int $navigationSort = 15;

    public int $shopId = 0;

    public function mount(): void
    {
        $shop = Shop::query()->where('is_active', true)->orderBy('id')->first();
        $this->shopId = $shop?->id ?? 0;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    public function recu(int $tableSessionId): void
    {
        if ($this->shopId === 0) {
            return;
        }

        $session = TableSession::query()
            ->whereKey($tableSessionId)
            ->where('shop_id', $this->shopId)
            ->first();
        if ($session === null) {
            return;
        }

        try {
            app(RecuPlacedOrdersForSessionAction::class)->execute(
                $this->shopId,
                $tableSessionId,
                (int) $session->session_revision
            );
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('rad_table.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function addition(int $tableSessionId): void
    {
        if ($this->shopId === 0) {
            return;
        }

        $session = TableSession::query()
            ->whereKey($tableSessionId)
            ->where('shop_id', $this->shopId)
            ->first();
        if ($session === null) {
            return;
        }

        try {
            app(RecordAdditionPrintForSessionAction::class)->execute(
                $this->shopId,
                $tableSessionId,
                (int) $session->session_revision
            );
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('rad_table.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function checkout(int $tableSessionId): void
    {
        if ($this->shopId === 0) {
            return;
        }

        $session = TableSession::query()
            ->whereKey($tableSessionId)
            ->where('shop_id', $this->shopId)
            ->first();
        if ($session === null) {
            return;
        }

        try {
            app(CheckoutTableSessionAction::class)->execute(
                $this->shopId,
                $tableSessionId,
                (int) $session->session_revision
            );
        } catch (Throwable $e) {
            Notification::make()
                ->title(__('rad_table.action_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'tiles' => $this->buildTiles(),
            'shopName' => $this->resolveShopName(),
        ];
    }

    private function resolveShopName(): string
    {
        if ($this->shopId === 0) {
            return '';
        }

        return (string) (Shop::query()->whereKey($this->shopId)->value('name') ?? '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildTiles(): array
    {
        if ($this->shopId === 0) {
            return [];
        }

        $resolver = app(RadTableTileResolver::class);

        return RestaurantTable::query()
            ->where('shop_id', $this->shopId)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->with(['activeSession.posOrders'])
            ->get()
            ->map(function (RestaurantTable $table) use ($resolver): array {
                $session = $table->activeSession;
                $color = $resolver->resolveColor($session);
                $orders = $session instanceof TableSession
                    ? $resolver->relevantOrders($session)
                    : collect();

                $hasPlaced = $orders->contains(fn ($o): bool => $o->status === OrderStatus::Placed);

                return [
                    'table_id' => $table->id,
                    'table_name' => $table->name,
                    'session_id' => $session?->id,
                    'color' => $color->value,
                    'badge_label' => $this->badgeLabelFor($color),
                    'total_minor' => $resolver->sessionTotalMinor($session),
                    'total_label' => $this->formatMinor($resolver->sessionTotalMinor($session)),
                    'dwell_label' => $this->formatDwell($session),
                    'can_recu' => $session !== null && $hasPlaced,
                    'can_addition' => $session !== null && ! $hasPlaced,
                    'can_checkout' => $session !== null && ! $hasPlaced,
                ];
            })
            ->all();
    }

    private function badgeLabelFor(RadTableTileColor $color): string
    {
        return match ($color) {
            RadTableTileColor::White => __('rad_table.badge_vacant'),
            RadTableTileColor::Red => __('rad_table.badge_unacked'),
            RadTableTileColor::Yellow => __('rad_table.badge_printed'),
            RadTableTileColor::Green => __('rad_table.badge_kitchen_sent'),
        };
    }

    private function formatMinor(int $minor): string
    {
        $divisor = 1000;

        return number_format($minor / $divisor, 3, '.', ' ').' TND';
    }

    private function formatDwell(?TableSession $session): string
    {
        if ($session === null || $session->opened_at === null) {
            return '—';
        }

        $mins = $session->opened_at->diffInMinutes(now());

        return __('rad_table.dwell_minutes', ['count' => $mins]);
    }
}
