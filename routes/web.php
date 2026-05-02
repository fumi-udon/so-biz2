<?php

use App\Filament\Pages\TableDashboard;
use App\Http\Controllers\ClientInventoryController;
use App\Http\Controllers\CloseCheckController;
use App\Http\Controllers\Kds\KdsV2Controller;
use App\Http\Controllers\KdsAuthController;
use App\Http\Controllers\MyPageController;
use App\Http\Controllers\NewsNoteController;
use App\Http\Controllers\Pos2\Pos2AuthController;
use App\Http\Controllers\Pos2\Pos2BridgeController;
use App\Http\Controllers\Pos2\Pos2Controller;
use App\Http\Controllers\Pos2\Pos2DevController;
use App\Http\Controllers\Pos2\Pos2SessionController;
use App\Http\Controllers\Pos2\TableSessionCustomerController;
use App\Http\Middleware\SetGuestLocale;
use App\Livewire\ClientOrderForm;
use App\Livewire\FrontendDailyClose;
use App\Livewire\GuestOrder\MenuPage as GuestMenuPage;
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
use Illuminate\Support\Facades\Auth;
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

Route::get('/kds/login', [KdsAuthController::class, 'showLoginForm'])->name('kds.login');
Route::post('/kds/login', [KdsAuthController::class, 'login'])->name('kds.login.submit');

Route::middleware('kds.auth')->group(function () {
    Route::get('/kds', fn () => redirect()->route('kds2.index'))->name('kds.dashboard');
});

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
// 旧 Presence 独立ページ → My page へ 302 リダイレクト（ブックマーク互換）
Route::get('/mypage/attendance', function (Request $request) {
    $params = array_filter([
        'staff_id' => $request->integer('staff_id') ?: null,
        'month' => $request->input('month') ?: null,
    ]);

    return redirect()->route('mypage.index', $params, 302);
})->name('mypage.attendance');

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

// ── Guest mobile order UI ──────────────────────────────────────────────────────
// Isolated from Filament/admin. SetGuestLocale resolves fr|en from Accept-Language.
// Ver2: tenantSlug resolved from tableToken directly (opaque signed token).
Route::middleware(SetGuestLocale::class)
    ->group(function () {
        Route::get(
            '/guest/menu/{tenantSlug}/{tableToken}',
            GuestMenuPage::class
        )->name('guest.menu');
    });

// ── Isolated Epson hardware sanity check (not wired into POS / Livewire) ─────
Route::get('/printer-test', function () {
    return view('printer-test');
})->name('printer-test');

// 旧 POS ブックマーク互換: /pos → POS V2（認証済みスタッフのみ）
Route::middleware('auth')->get('/pos', function () {
    return redirect()->route('pos2.index');
})->name('pos.redirect-to-v2');

Route::get('/pos/receipt-preview', function (Request $request) {
    abort_unless(Auth::check() || $request->session()->get('pos2_authenticated') === true, 403);

    $shopId = max(0, (int) $request->query('shop_id', 0));
    $tableSessionId = max(0, (int) $request->query('table_session_id', 0));
    $expectedRevision = max(0, (int) $request->query('expected_revision', 0));
    $intent = (string) $request->query('intent', 'addition');

    abort_unless($shopId > 0 && $tableSessionId > 0, 404);

    return view('pos.receipt-preview-page', [
        'shopId' => $shopId,
        'tableSessionId' => $tableSessionId,
        'expectedRevision' => $expectedRevision,
        'intent' => $intent,
        'posMainEscapeUrl' => TableDashboard::getUrl(),
    ]);
})->name('pos.receipt-preview.page');

Route::get('/history_cloture', function (Request $request) {
    abort_unless(Auth::check() || $request->session()->get('pos2_authenticated') === true, 403);

    return view('pos.history-cloture-page');
})->name('pos.history-cloture.page');

// ── KDS V2 (Vue 3 + Pinia SPA) ────────────────────────────────────────────────
// 既存 KDS (Livewire) とは完全に並行稼働する。POS 不可侵原則は適用しない。
// .cursorrules § [RULE] KDS V2 SPA Architecture に従う。
Route::prefix('kds2')->middleware(['web', 'kds.auth'])->group(function () {
    Route::get('/', [KdsV2Controller::class, 'index'])->name('kds2.index');
    Route::get('/api/tickets', [KdsV2Controller::class, 'tickets'])->name('kds2.api.tickets');
    Route::post('/api/tickets/{id}/served', [KdsV2Controller::class, 'markServed'])->name('kds2.api.mark-served');
    Route::get('/api/master', [KdsV2Controller::class, 'master'])->name('kds2.api.master');
    Route::get('/api/dictionary', [KdsV2Controller::class, 'dictionary'])->name('kds2.api.dictionary');
});

// ── POS2 auth (Filament / Livewire から完全分離) ───────────────────────────────
Route::prefix('pos2')->name('pos2.')->group(function () {
    Route::get('/login', [Pos2AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [Pos2AuthController::class, 'login'])->name('login.submit');
    Route::post('/logout', [Pos2AuthController::class, 'logout'])->name('logout');

    Route::middleware('pos2.auth')->group(function () {
        Route::get('/', [Pos2Controller::class, 'index'])->name('index');
        Route::get('/api/bootstrap', [Pos2Controller::class, 'bootstrap'])->name('api.bootstrap');
        Route::post('/api/orders', [Pos2Controller::class, 'submitOrderStub'])->name('api.orders.stub');
        Route::get('/api/table-dashboard', [Pos2SessionController::class, 'tableDashboard'])->name('api.table-dashboard');
        Route::get('/api/sessions/{session}/orders', [Pos2SessionController::class, 'sessionOrders'])->name('api.sessions.orders');
        Route::post('/api/sessions/{session}/customer', [TableSessionCustomerController::class, 'update'])->name('api.sessions.customer');
        Route::post('/api/sessions/{session}/orders', [Pos2SessionController::class, 'submitDraftOrders'])->name('api.sessions.orders.submit');
        Route::post('/api/sessions/{session}/recu-staff', [Pos2SessionController::class, 'recuStaff'])->name('api.sessions.recu-staff');
        Route::post('/api/sessions/{session}/order-lines/{orderLine}/delete', [Pos2SessionController::class, 'deleteOrderLine'])->name('api.sessions.order-lines.delete');
        // 空卓から注文: table_id を渡すとサーバーが getOrCreate してセッション確定後に 201 を返す
        Route::post('/api/tables/{table}/orders', [Pos2SessionController::class, 'submitDraftOrdersForTable'])->name('api.tables.orders.submit');
        Route::post('/tables/move', [Pos2SessionController::class, 'moveTable'])->name('tables.move');
        /** 開発のみ: 当該ショップの卓セッション・注文を DB から削除（config app.pos2_debug） */
        Route::post('/api/dev/purge-floor-data', [Pos2DevController::class, 'purgeFloorData'])->name('api.dev.purge-floor-data');

        /** Phase 5: 旧 Livewire（ReceiptPreview / ClotureModal）を別タブで開く薄いブリッジ */
        Route::get('/bridge/sessions/{session}/addition', [Pos2BridgeController::class, 'addition'])->name('bridge.sessions.addition');
        Route::get('/bridge/sessions/{session}/cloture', [Pos2BridgeController::class, 'cloture'])->name('bridge.sessions.cloture');
        Route::get('/bridge/sessions/{session}/receipt', [Pos2BridgeController::class, 'receipt'])->name('bridge.sessions.receipt');
    });
});
