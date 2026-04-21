<?php

/**
 * POS 現場向け設定（プリンター接続タイムアウト等）。
 * プリンターの IP / ポート等の既定値は {@see config/pos_printer.php} を参照。
 */
$posPrinter = require __DIR__.'/pos_printer.php';

return [
    'printer' => array_merge(
        $posPrinter['defaults'] ?? [],
        [
            /** ePOSDevice WebSocket 接続確立までの上限（ミリ秒） */
            'connect_timeout_ms' => 20_000,
            /** 最終ジョブ完了後、この時間操作がなければ disconnect（他端末・メンテ用にポート解放） */
            'idle_disconnect_ms' => 60_000,
            /** 公式 PrinterObject.js 準拠: DEVICE_IN_USE 時の createDevice 再試行 */
            'device_in_use_retry_max' => 5,
            'device_in_use_retry_delay_ms' => 3_000,
            /** ePOS createDevice の buffer フラグ（ReceiptDesigner 既定と同様 false） */
            'buffer' => false,
        ]
    ),
];
