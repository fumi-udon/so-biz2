<?php

use App\Http\Controllers\ClientInventoryController;
use App\Http\Controllers\CloseCheckController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\NewsNoteController;
use App\Livewire\ClientOrderForm;
use App\Livewire\FrontendDailyClose;
use App\Livewire\TimecardForm;
use App\Models\Attendance;
use App\Models\NewsNote;
use App\Models\Staff;
use App\Services\WeeklyShiftGridService;
use App\Support\AbsenceScope;
use App\Support\BusinessDate;
use App\Support\StoreHolidaySetting;
use App\Support\TipAttendanceScope;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    $request->session()->forget('mypage_staff_id');

    // ── スタッフリスト（MyPage モーダル / Note モーダル用） ─────────────
    $mypageStaffList = Staff::query()->where('is_active', true)->orderBy('name')->get();
    $recentNews = NewsNote::recentDays(5);

    // ── 当日チップ対象者（打刻 + 申請 + 非剥奪） ─────────────────────────
    $today = BusinessDate::current()->toDateString();
    $tipLunchAppliers = TipAttendanceScope::applyGoldenFormula(
        Attendance::query()->whereDate('date', $today),
        'lunch',
    )
        ->with('staff:id,name')
        ->get()
        ->pluck('staff')
        ->filter()
        ->values();
    $tipDinnerAppliers = TipAttendanceScope::applyGoldenFormula(
        Attendance::query()->whereDate('date', $today),
        'dinner',
    )
        ->with('staff:id,name')
        ->get()
        ->pluck('staff')
        ->filter()
        ->values();

    // ── 本日の人員配置（週次シフト表と同一集計・welcome 用） ─────────────
    $shiftGridData = app(WeeklyShiftGridService::class)->build();
    $todayDayKey = $shiftGridData['todayDayKey'];
    $emptyShiftBlock = [
        'assignments' => [],
        'counts' => ['kitchen' => 0, 'hall' => 0, 'other' => 0],
        'live_extras' => [],
    ];
    $todayShiftPanel = [
        'dayLabel' => $shiftGridData['dayLabels'][$todayDayKey] ?? $todayDayKey,
        'dateLabel' => Carbon::parse($today)->format('d/m/Y'),
        'lunch' => $shiftGridData['shiftGrid'][$todayDayKey]['lunch'] ?? $emptyShiftBlock,
        'dinner' => $shiftGridData['shiftGrid'][$todayDayKey]['dinner'] ?? $emptyShiftBlock,
    ];

    // ── 勤怠ガント用集計 ─────────────────────────────────────────────────
    $bd = BusinessDate::current();
    $ganttMonthStart = $bd->copy()->startOfMonth()->toDateString();
    $ganttMonthEnd = $bd->copy()->toDateString();
    $ganttMonthLabel = $bd->copy()->format('M Y');

    $ganttAllStaff = Staff::query()
        ->where('is_active', true)
        ->whereHas('jobLevel', fn ($q) => $q->where('level', '!=', 10))
        ->with('jobLevel')
        ->orderBy('name')
        ->get();

    // 当月 Attendance を一括取得してスタッフ別 + 日付別にインデックス
    $ganttAllAttendances = Attendance::query()
        ->whereIn('staff_id', $ganttAllStaff->pluck('id'))
        ->whereBetween('date', [$ganttMonthStart, $ganttMonthEnd])
        ->get();

    $ganttAttendanceMap = [];
    foreach ($ganttAllAttendances as $att) {
        $dateStr = Carbon::parse($att->date)->toDateString();
        $ganttAttendanceMap[$att->staff_id][$dateStr] = $att;
    }

    $ganttHolidaySet = StoreHolidaySetting::dateSet();
    $ganttStaffIds = $ganttAllStaff->pluck('id')->all();
    $ganttAbsenceMap = AbsenceScope::loadAbsenceMapForStaffInRange($ganttStaffIds, $ganttMonthStart, $ganttMonthEnd);

    $ganttRows = $ganttAllStaff->map(function ($s) use ($ganttAttendanceMap, $ganttMonthStart, $ganttMonthEnd, $ganttHolidaySet, $ganttAbsenceMap) {
        $staffAttByDate = $ganttAttendanceMap[$s->id] ?? [];

        // 遅刻カウント
        $late = collect($staffAttByDate)->filter(fn ($r) => (int) ($r->late_minutes ?? 0) > 0)->count();

        // 欠勤カウント（AbsenceScope: 休業日・出勤・確定欠勤）
        $absent = 0;
        $absentDates = [];
        $cursor = Carbon::parse($ganttMonthStart);
        $endCarbon = Carbon::parse($ganttMonthEnd);
        while ($cursor->lte($endCarbon)) {
            $d = $cursor->toDateString();
            $row = $staffAttByDate[$d] ?? null;
            $hasAbs = isset($ganttAbsenceMap[$s->id][$d]);
            if (AbsenceScope::resolveDay($d, $row, $ganttHolidaySet, $hasAbs) === AbsenceScope::STATUS_ABSENT) {
                $absent++;
                $absentDates[] = $d;
            }
            $cursor->addDay();
        }

        return [
            'staff' => $s,
            'late' => $late,
            'absent' => $absent,
            'absent_dates' => $absentDates,
            'total' => $late + $absent,
        ];
    })->sortByDesc('total')->values();

    $ganttBravo = $ganttRows->filter(fn ($r) => $r['total'] === 0)->values();
    $ganttProblematic = $ganttRows->filter(fn ($r) => $r['total'] > 0)->values();
    $ganttMaxVal = max((int) ($ganttProblematic->max('total') ?? 0), 1);

    return view('welcome', compact(
        'mypageStaffList',
        'recentNews',
        'today',
        'tipLunchAppliers',
        'tipDinnerAppliers',
        'todayShiftPanel',
        'ganttMonthLabel',
        'ganttRows',
        'ganttBravo',
        'ganttProblematic',
        'ganttMaxVal',
    ));
})->name('home');

Route::get('/order/{table_number}', ClientOrderForm::class)->name('order.table');

Route::get('/timecard', TimecardForm::class)->name('timecard.index');

Route::get('/daily-close', FrontendDailyClose::class)->name('daily-close');

Route::get('/close-check', [CloseCheckController::class, 'index'])->name('close-check.index');
Route::post('/close-check/process', [CloseCheckController::class, 'process'])->name('close-check.process');
Route::get('/close-check/success', [CloseCheckController::class, 'success'])->name('close-check.success');

Route::get('/mypage', [MyPageController::class, 'index'])->name('mypage.index');
Route::get('/mypage/reauth', [MyPageController::class, 'reauthenticate'])->name('mypage.reauth');
Route::post('/mypage/open', [MyPageController::class, 'openByPin'])->name('mypage.open');
Route::post('/mypage', [MyPageController::class, 'store'])->name('mypage.store');
Route::post('/mypage/auto-logout', [MyPageController::class, 'autoLogout'])->name('mypage.auto-logout');
Route::get('/mypage/attendance', [MyPageController::class, 'attendance'])->name('mypage.attendance');
Route::post('/mypage/attendance', [MyPageController::class, 'updateAttendance'])->name('mypage.attendance.update');
Route::post('/mypage/attendance/authorize-edit', [MyPageController::class, 'authorizeEdit'])->name('mypage.attendance.authorize-edit');
Route::post('/mypage/attendance/patch', [MyPageController::class, 'patchAttendance'])->name('mypage.attendance.patch');

// 固定パスを {id} の前に並べること（ルータは上から順にマッチするため）
Route::post('/news/auth', [NewsNoteController::class, 'auth'])->name('news.auth');
Route::post('/news/logout', [NewsNoteController::class, 'logout'])->name('news.logout');
Route::get('/news/manage', [NewsNoteController::class, 'manage'])->name('news.manage');
Route::post('/news', [NewsNoteController::class, 'store'])->name('news.store');
Route::post('/news/{id}', [NewsNoteController::class, 'update'])->name('news.update');
Route::post('/news/{id}/delete', [NewsNoteController::class, 'destroy'])->name('news.destroy');

Route::prefix('inventory')->name('inventory.')->group(function () {
    Route::get('/', [ClientInventoryController::class, 'index'])->name('index');
    Route::get('/input/{timing}/{staff_id}', [ClientInventoryController::class, 'input'])->name('input');
    Route::post('/store', [ClientInventoryController::class, 'store'])->name('store');
});
