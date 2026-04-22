<?php

use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// test-channel は public（Channel）のため、Broadcast::channel() で定義しないこと。
// private / presence 用の認証のみここに書く。誤って test-channel を登録すると private 扱いになり 403 の原因になる。

// Filament DatabaseNotifications が接続するユーザー専用 Private チャンネルの認可
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// V4: `docs/technical_contract_v4.md` §3.3 — 店舗スコープの Pos 通知（`App\Events\Pos\PosOrderPlaced`）
Broadcast::channel('pos.shop.{shopId}', function (?User $user, int|string $shopId) {
    if (! $user instanceof User) {
        return false;
    }
    $id = (int) $shopId;
    if ($id < 1) {
        return false;
    }

    if ((bool) config('pos.enforce_realtime_shop_scope', true)) {
        $scopeShopId = (int) config('pos.default_shop_id', 3);
        if ($scopeShopId > 0 && $id !== $scopeShopId) {
            return false;
        }
    }

    $shop = Shop::query()->find($id);
    if ($shop === null) {
        return false;
    }

    return $user->can('view', $shop);
});

Broadcast::channel('rt.shop.{shopId}.orders', function (?User $user, int|string $shopId) {
    if (! $user instanceof User) {
        return false;
    }

    $id = (int) $shopId;
    if ($id < 1) {
        return false;
    }

    if ((bool) config('pos.enforce_realtime_shop_scope', true)) {
        $scopeShopId = (int) config('pos.default_shop_id', 3);
        if ($scopeShopId > 0 && $id !== $scopeShopId) {
            return false;
        }
    }

    $shop = Shop::query()->find($id);
    if ($shop === null) {
        return false;
    }

    return $user->can('view', $shop);
});
