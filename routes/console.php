<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// attendance_edit_logs を2か月以上前のもの毎日自動削除
Schedule::call(function () {
    \App\Models\AttendanceEditLog::where('created_at', '<', now()->subMonths(2))->delete();
})->daily()->name('purge-attendance-edit-logs')->withoutOverlapping();
