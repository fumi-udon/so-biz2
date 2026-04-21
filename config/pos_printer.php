<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default POS printer (per shop until DB row exists)
    |--------------------------------------------------------------------------
    |
    | Used when shop_printer_settings has no row for the shop. DR-01 prefers
    | HTTPS (crypto) on the printer; adjust port to match device firmware.
    |
    */
    'defaults' => [
        'printer_ip' => env('POS_PRINTER_IP', '192.168.1.200'),
        'printer_port' => env('POS_PRINTER_PORT', '8043'),
        'device_id' => env('POS_PRINTER_DEVICE_ID', 'local_printer'),
        'crypto' => filter_var(env('POS_PRINTER_CRYPTO', true), FILTER_VALIDATE_BOOL),
        'timeout_ms' => (int) env('POS_PRINTER_TIMEOUT_MS', 10_000),
    ],
];
