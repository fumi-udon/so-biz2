<?php

use Illuminate\Support\Facades\Broadcast;

// test-channel は public（Channel）のため、Broadcast::channel() で定義しないこと。
// private / presence 用の認証のみここに書く。誤って test-channel を登録すると private 扱いになり 403 の原因になる。

// Filament DatabaseNotifications が接続するユーザー専用 Private チャンネルの認可
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
