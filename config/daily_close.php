<?php

return [

    /*
    |--------------------------------------------------------------------------
    | レジ締め「管理者 Door」用 PIN
    |--------------------------------------------------------------------------
    |
    | 空のときは Door から参照モードに入れません（未設定の案内のみ）。
    | スタッフ画面には許容上限を出さず、この PIN を知る管理者だけが参照できます。
    |
    */
    'door_secret' => env('DAILY_CLOSE_DOOR_SECRET', ''),

    /*
    |--------------------------------------------------------------------------
    | レジ締「Get recettes」用 外部注文 API
    |--------------------------------------------------------------------------
    |
    | Http::withoutVerifying() で取得（証明書検証スキップ）。本番では URL を確認すること。
    |
    | timeout … 1 リクエストあたりの最大待機（秒）。外部が応答しない・遅いときに PHP プロセスを
    |   無制限に占有しないよう、必ず php-fpm の max_execution_time より短くすること。
    | connect_timeout … TCP 接続確立までの上限（秒）。相手が落ちている・到達不能なときに
    |   長時間ブロックしない。
    |
    | processing_timezone … 営業日の 0:00〜23:59 の解釈に使う TZ（空なら app.timezone）。
    |   旧 Jesser サーバの date_default_timezone_get() と合わせると境界ずれを防げる。
    | use_legacy_strtotime … true のときのみ Jesser と同じ strtotime 集計（比較用）。
    | sum_line_keys … オーダー1件あたりの金額キー（先頭から存在するキーを採用）。既定 total＝POS の会計合計（明細の再計算ではない）。
    | debug_aggregate_log … true で JSON は出さず件数・合計のみログ（原因切り分け用）。
    |
    */
    'orders_api' => [
        'url' => env('DAILY_CLOSE_ORDERS_API_URL', 'https://bistronippon.com/api/orders'),
        'store' => env('DAILY_CLOSE_ORDERS_API_STORE', 'main'),
        'timeout' => (int) env('DAILY_CLOSE_ORDERS_API_TIMEOUT', 20),
        'connect_timeout' => (int) env('DAILY_CLOSE_ORDERS_API_CONNECT_TIMEOUT', 5),
        'processing_timezone' => env('DAILY_CLOSE_ORDERS_API_TZ', ''),
        'use_legacy_strtotime' => filter_var(env('DAILY_CLOSE_ORDERS_API_LEGACY_STRTOTIME', false), FILTER_VALIDATE_BOOLEAN),
        'sum_line_keys' => array_values(array_filter(array_map('trim', explode(',', (string) env('DAILY_CLOSE_ORDERS_API_SUM_KEYS', 'total'))))),
        'debug_aggregate_log' => filter_var(env('DAILY_CLOSE_ORDERS_API_DEBUG_LOG', false), FILTER_VALIDATE_BOOLEAN),
    ],

    /*
    |--------------------------------------------------------------------------
    | orders_api 集計の前提（コードと揃える）
    |--------------------------------------------------------------------------
    |
    | - Midi: end_date が営業日 00:00:01 〜 18:00:00（同一 TZ 上で比較）
    | - Soir: 18:00:01 〜 23:59:59
    | - end_date の文字列は processing_timezone（空なら app.timezone）のローカル時刻として解釈する
    |
    */

];
