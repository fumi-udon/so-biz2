<?php

/**
 * POS 現場向け設定（プリンター接続タイムアウト等）。
 * プリンターの IP / ポート等の既定値は {@see config/pos_printer.php} を参照。
 */
$posPrinterPath = __DIR__.'/pos_printer.php';
if (file_exists($posPrinterPath)) {
    $posPrinter = require $posPrinterPath;
} else {
    $posPrinter = [
        'defaults' => [
            'printer_ip' => '192.168.1.101',
            'printer_port' => '8043',
            'device_id' => 'local_printer',
            'crypto' => true,
            'timeout_ms' => 10_000,
        ],
    ];
}

/** 既定 TVA 率（%）。TVA_TN を優先し、未設定時は POS_RECEIPT_VAT_DEFAULT。 */
$defaultTvaRate = (float) env('TVA_TN', env('POS_RECEIPT_VAT_DEFAULT', 19));

return [
    /** POS ダッシュボード既定店舗（未設定時は 3: Bistronippon）。 */
    'default_shop_id' => (int) env('POS_DEFAULT_SHOP_ID', 3),
    /**
     * true の場合、リアルタイム購読は `default_shop_id` のみ許可。
     * prefix 分離運用（bnops_ など）で他店舗混信を防ぐ安全弁。
     */
    'enforce_realtime_shop_scope' => (bool) env('POS_ENFORCE_REALTIME_SHOP_SCOPE', true),
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
            /**
             * false のとき物理プリンタ／pos-trigger-print をスキップ（開発用）。会計・プレビュー完了イベントは維持。
             */
            'physical_enabled' => filter_var(env('EPONS_GOGO_PRINTER', true), FILTER_VALIDATE_BOOL),
        ]
    ),
    /**
     * レシート印字（TM-m30 / UTF-8）。住所・MF は単店舗向けデフォルト。
     */
    'receipt' => [
        'default_tva_rate' => $defaultTvaRate,
        'default_vat_percent' => $defaultTvaRate,
        'address_lines' => array_values(array_filter(array_map('trim', explode("\n", (string) env('POS_RECEIPT_ADDRESS', 'La Marsa, Tunis'))))),
        'shop_phone' => env('POS_RECEIPT_PHONE', ''),
        'mf_number' => env('POS_RECEIPT_MF', ''),
        'footer_thanks_lines' => ['Merci de votre visite'],
        /** ePOS レシート上部タイトル（未設定時はペイロードの shop_name） */
        'brand_name' => env('BRAND_NAME', ''),
        /** 印字ヘッダー住所・TEL（未設定時は POS_RECEIPT_* / ペイロード） */
        'epson_address' => env('EPSON_ADRESSE'),
        'epson_tel' => env('EPSON_TEL'),
    ],
];
