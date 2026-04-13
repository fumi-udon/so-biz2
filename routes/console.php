<?php

use App\Models\AttendanceEditLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// attendance_edit_logs を2か月以上前のもの毎日自動削除
Schedule::call(function () {
    AttendanceEditLog::where('created_at', '<', now()->subMonths(2))->delete();
})->daily()->name('purge-attendance-edit-logs')->withoutOverlapping();

// 退勤打刻漏れの自動補完（深夜バッチ）
// timezone を明示して BusinessDate と整合させる（深夜01:00 = 飲食店の前営業日）
Schedule::command('app:auto-clock-out')
    ->dailyAt('01:00')
    ->timezone(config('app.business_timezone'))
    ->withoutOverlapping()
    ->name('auto-clock-out');
