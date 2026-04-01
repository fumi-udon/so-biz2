<?php

$parts = array_values(array_filter(array_map('trim', explode(',', (string) (env('RESTAURANT_TABLES') ?: '1,2,3,4')))));

return [

    /*
    |--------------------------------------------------------------------------
    | テーブル番号（顧客注文フォーム・厨房モニター共通）
    |--------------------------------------------------------------------------
    |
    | ClientOrderForm のバリデーションと OrderMonitor の表示列が同じ配列を参照する。
    | RESTAURANT_TABLES はカンマ区切り（例: 1,2,3,4,5）。空・不正時は 1〜4 にフォールバック。
    |
    */
    'tables' => $parts !== [] ? array_map('strval', $parts) : ['1', '2', '3', '4'],

];
