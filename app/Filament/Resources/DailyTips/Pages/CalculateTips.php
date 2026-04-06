<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Models\Attendance;
use App\Models\DailyTip;
use App\Models\Finance;
use App\Models\Staff;
use App\Support\DailyTipAuditContext;
use App\Support\DailyTipAuditLogger;
use App\Services\TipCalculationService;
use App\Support\BusinessDate;
use App\Support\TipAttendanceScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CalculateTips extends Page
{
    protected static string $resource = DailyTipResource::class;

    protected static string $view = 'filament.resources.daily-tips.pages.calculate-tips';

    protected static ?string $title = 'チップ計算';

    /** ページ標準ヘッダーを出さず、本文から入力を開始する */
    protected ?string $heading = '';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public float $distributed_total = 0.0;

    public float $weekly_total = 0.0;

    public float $monthly_total = 0.0;

    /** @var array<int, array<string, mixed>> */
    public array $recent_averages = [];

    public bool $managerPinVerified = false;

    public ?int $managerStaffId = null;

    public string $managerPinInput = '';

    public function mount(): void
    {
        $this->form->fill([
            'business_date' => BusinessDate::current()->toDateString(),
            'shift' => 'lunch',
            'total_amount' => '0',
            'selected_staff_id' => null,
            'manager_staff_id' => null,
            'manager_pin' => '',
        ]);
        $this->loadRows();
        $this->hydrateTipAmountFromFinance();
        $this->recalculateRows();
        $this->refreshAnalytics();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make([
                    'default' => 1,
                    'md' => 2,
                ])
                    ->extraAttributes([
                        'class' => 'gap-y-2 gap-x-2',
                    ])
                    ->schema([
                        Section::make()
                            ->heading(null)
                            ->compact()
                            ->schema([
                                DatePicker::make('business_date')
                                    ->label('Date')
                                    ->required()
                                    ->native(true)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn () => $this->afterConditionChanged())
                                    ->extraInputAttributes(['class' => 'py-1.5 text-sm']),
                            ])
                            ->extraAttributes([
                                'class' => 'rounded-xl border-2 border-amber-200 bg-amber-50/50 shadow-sm ring-1 ring-amber-200/80 dark:border-amber-700 dark:bg-amber-950/30 dark:ring-amber-900/40',
                            ]),
                        Section::make()
                            ->heading(null)
                            ->compact()
                            ->schema([
                                Select::make('shift')
                                    ->label('Shift')
                                    ->options([
                                        'lunch' => 'Midi (L)',
                                        'dinner' => 'Soir (D)',
                                    ])
                                    ->required()
                                    ->native(true)
                                    ->live(debounce: 500)
                                    ->afterStateUpdated(fn () => $this->afterConditionChanged())
                                    ->extraInputAttributes(['class' => 'py-1.5 text-sm']),
                            ])
                            ->extraAttributes([
                                'class' => 'rounded-xl border-2 border-indigo-200 bg-indigo-50/50 shadow-sm ring-1 ring-indigo-200/80 dark:border-indigo-700 dark:bg-indigo-950/30 dark:ring-indigo-900/40',
                            ]),
                    ]),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('Tip total (DT)')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->required()
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->recalculateRows())
                            ->extraInputAttributes([
                                'class' => 'py-2 text-2xl font-semibold tabular-nums leading-tight',
                                'step' => '0.001',
                                'inputmode' => 'decimal',
                            ]),
                    ])
                    ->extraAttributes([
                        'class' => 'rounded-xl border-2 border-sky-400 bg-sky-50/50 shadow-sm ring-1 ring-sky-300/70 dark:border-sky-600 dark:bg-sky-950/40 dark:ring-sky-800/50',
                    ]),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->schema([
                        Select::make('manager_staff_id')
                            ->label('Manager')
                            ->options(fn (): array => $this->managerStaffOptions())
                            ->placeholder('Choisir')
                            ->native(true)
                            ->visible(fn () => $this->needsManagerPin())
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->managerStaffId = $state === '' || $state === null ? null : (int) $state;
                                $this->managerPinVerified = false;
                            }),
                        TextInput::make('manager_pin')
                            ->label('PIN manager')
                            ->password()
                            ->maxLength(4)
                            ->visible(fn () => $this->needsManagerPin())
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->managerPinInput = (string) ($state ?? '');
                                $this->managerPinVerified = false;
                            }),
                        Select::make('selected_staff_id')
                            ->label('Ajouter staff')
                            ->options(fn (): array => $this->availableStaffOptions)
                            ->placeholder('— 追加 —')
                            ->native(true)
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->afterStaffSelectChanged())
                            ->extraInputAttributes(['class' => 'py-1.5 text-sm']),
                    ])
                    ->extraAttributes([
                        'class' => 'rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900',
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<int, string>
     */
    public function getAvailableStaffOptionsProperty(): array
    {
        $selected = array_map(fn (array $row): int => (int) $row['staff_id'], $this->rows);
        $businessDate = $this->data['business_date'] ?? BusinessDate::current()->toDateString();
        $shift = $this->data['shift'] ?? 'lunch';

        $eligibleIds = TipAttendanceScope::applyGoldenFormula(
            Attendance::query()->whereDate('date', $businessDate),
            $shift === 'dinner' ? 'dinner' : 'lunch',
        )
            ->whereNotIn('staff_id', $selected)
            ->pluck('staff_id');

        return Staff::query()
            ->where('is_active', true)
            ->whereIn('id', $eligibleIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    protected function afterConditionChanged(): void
    {
        $this->loadRows();
        $this->hydrateTipAmountFromFinance();
        $this->recalculateRows();
        $this->refreshAnalytics();
    }

    protected function afterStaffSelectChanged(): void
    {
        $raw = $this->data['selected_staff_id'] ?? null;
        if ($raw === null || $raw === '') {
            return;
        }

        $this->addSelectedStaff((int) $raw);
        $this->data['selected_staff_id'] = null;
    }

    public function addSelectedStaff(int $staffId): void
    {
        foreach ($this->rows as $row) {
            if ((int) $row['staff_id'] === $staffId) {
                return;
            }
        }

        $staff = Staff::query()
            ->where('is_active', true)
            ->with('jobLevel')
            ->find($staffId);

        if (! $staff) {
            return;
        }

        // tip_weight_override があれば優先、なければ jobLevel.default_weight にフォールバック
        $businessDate = $this->data['business_date'] ?? BusinessDate::current()->toDateString();
        $shift = $this->data['shift'] ?? 'lunch';
        $attendance = Attendance::query()
            ->where('staff_id', $staffId)
            ->whereDate('date', $businessDate)
            ->first();

        $eligible = $shift === 'lunch'
            ? ($attendance && TipAttendanceScope::lunchEligible($attendance))
            : ($attendance && TipAttendanceScope::dinnerEligible($attendance));
        if (! $eligible) {
            return;
        }

        $rawWeight = $attendance?->tip_weight_override
            ?? $staff->jobLevel?->default_weight;
        $base = $rawWeight === null
            ? TipCalculationService::DEFAULT_DISTRIBUTION_WEIGHT
            : (float) TipCalculationService::normalizeWeightScalar($rawWeight);
        $defaultWeight = $this->snapTipWeightToMasterGrid($base);

        $this->rows[] = [
            'staff_id' => $staff->id,
            'name' => $staff->name,
            'job_level' => $staff->jobLevel?->name ?? 'Unassigned',
            'weight' => $defaultWeight,
            'amount' => 0.0,
            'is_tardy_deprived' => false,
            'is_manual_added' => true,
            'note' => null,
        ];

        $this->recalculateRows();
    }

    public function removeStaff(int $staffId): void
    {
        $this->rows = array_values(array_filter(
            $this->rows,
            fn (array $row): bool => (int) $row['staff_id'] !== $staffId
        ));

        $this->recalculateRows();
    }

    /**
     * ネストした rows.* の更新でも再計算する。
     */
    public function updated($propertyName): void
    {
        if ($propertyName === 'rows') {
            return;
        }

        if (str_starts_with($propertyName, 'rows.')) {
            $this->recalculateRows();
        }
    }

    public function confirm(): void
    {
        $this->validate([
            'data.business_date' => ['required', 'date'],
            'data.shift' => ['required', 'in:lunch,dinner'],
            'data.total_amount' => ['required', 'numeric', 'min:0'],
        ], attributes: [
            'data.business_date' => '営業日',
            'data.shift' => 'シフト',
            'data.total_amount' => 'チップ総額',
        ]);

        $savedTotal = $this->normalizedTotalAmount();

        if ($this->needsManagerPin()) {
            $this->verifyManagerPinOrFail();
        }

        $businessDate = $this->data['business_date'] ?? null;
        $shift        = $this->data['shift'] ?? 'lunch';
        $flagField    = $shift === 'lunch' ? 'is_lunch_tip_applied' : 'is_dinner_tip_applied';
        $denyField    = $shift === 'lunch' ? 'is_lunch_tip_denied'  : 'is_dinner_tip_denied';
        $rowStaffIds  = array_map(fn (array $r): int => (int) $r['staff_id'], $this->rows);

        // staff_id => 確定 weight (0-100 整数) のマップ（tip_weight_override として Attendance に書き戻す用）
        $rowWeightMap = [];
        foreach ($this->rows as $row) {
            $w = $this->snapTipWeightToMasterGrid(
                (float) TipCalculationService::normalizeWeightScalar($row['weight'] ?? 0)
            );
            $rowWeightMap[(int) $row['staff_id']] = (int) round($w);
        }

        DB::transaction(function () use ($savedTotal, $businessDate, $shift, $flagField, $denyField, $rowStaffIds, $rowWeightMap): void {
            $tip = DailyTip::query()->updateOrCreate(
                [
                    'business_date' => $businessDate,
                    'shift' => $shift,
                ],
                [
                    'total_amount' => $savedTotal,
                ]
            );

            $beforeCount = (int) $tip->distributions()->count();
            $createdCount = 0;

            DailyTipAuditContext::suppressDistributionAudit(true);
            try {
                $tip->distributions()->delete();

                foreach ($this->rows as $row) {
                    $w = $this->snapTipWeightToMasterGrid(
                        (float) TipCalculationService::normalizeWeightScalar($row['weight'] ?? 0)
                    );
                    $tip->distributions()->create([
                        'staff_id' => (int) $row['staff_id'],
                        'weight' => round($w, 3),
                        'amount' => round((float) $row['amount'], 3),
                        'is_tardy_deprived' => (bool) $row['is_tardy_deprived'],
                        'is_manual_added' => (bool) $row['is_manual_added'],
                        'note' => $row['note'],
                    ]);
                    $createdCount++;
                }
            } finally {
                DailyTipAuditContext::suppressDistributionAudit(false);
            }

            // --- Attendance フラグ同期 ---
            // 確定前時点で pool に含まれていたスタッフ（打刻あり OR flag=true, かつ deny=false）を取得し revoke 対象を特定する。
            // flag=true のみ取得すると打刻のみ pool 入りスタッフが除外できないため、loadRows と同じ条件で取得する。
            $flaggedBefore = TipAttendanceScope::applyGoldenFormula(
                Attendance::query()->whereDate('date', $businessDate),
                $shift === 'dinner' ? 'dinner' : 'lunch',
            )
                ->pluck('staff_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            // $rows にいるスタッフ: flag を true に、deny をクリア（Attendance がなければ新規作成）。
            // 確定 weight を tip_weight_override として書き戻し、次回 loadRows 時に一致させる。
            foreach ($rowStaffIds as $staffId) {
                Attendance::query()->updateOrCreate(
                    ['staff_id' => $staffId, 'date' => $businessDate],
                    [
                        $flagField            => true,
                        $denyField            => false,
                        'tip_weight_override' => $rowWeightMap[$staffId] ?? null,
                    ]
                );
            }

            // $rows にいないが以前 pool にいたスタッフ: flag を false, deny を true に。
            $toRevoke = array_values(array_diff($flaggedBefore, $rowStaffIds));
            if ($toRevoke !== []) {
                Attendance::query()
                    ->whereDate('date', $businessDate)
                    ->whereIn('staff_id', $toRevoke)
                    ->update([$flagField => false, $denyField => true]);
            }

            DailyTipAuditLogger::write(
                'distribution_recalculated',
                $tip->business_date?->toDateString(),
                $tip->shift,
                [
                    'daily_tip_id' => $tip->id,
                    'removed_count' => $beforeCount,
                    'created_count' => $createdCount,
                    'final_total_amount' => (float) $tip->total_amount,
                ]
            );

            DailyTipAuditLogger::write(
                'attendance_tip_flag_synced',
                $businessDate,
                $shift,
                [
                    'flagged_staff_ids' => $rowStaffIds,
                    'revoked_staff_ids' => $toRevoke,
                ]
            );
        });

        Notification::make()
            ->title('Répartition enregistrée')
            ->success()
            ->send();

        $this->refreshAnalytics();
    }

    protected function loadRows(): void
    {
        $businessDate = $this->data['business_date'] ?? BusinessDate::current()->toDateString();
        $shift = $this->data['shift'] ?? 'lunch';

        $attendances = TipAttendanceScope::applyGoldenFormula(
            Attendance::query()->whereDate('date', $businessDate),
            $shift === 'dinner' ? 'dinner' : 'lunch',
        )
            ->whereHas('staff')
            ->with(['staff' => fn ($query) => $query->with('jobLevel')])
            ->get()
            ->filter(fn (Attendance $attendance): bool => $attendance->staff !== null)
            ->unique('staff_id')
            ->values();

        $this->rows = $attendances->map(function (Attendance $attendance): array {
            $rawWeight = $attendance->tip_weight_override
                ?? $attendance->staff->jobLevel?->default_weight;
            $base = $rawWeight === null
                ? TipCalculationService::DEFAULT_DISTRIBUTION_WEIGHT
                : (float) TipCalculationService::normalizeWeightScalar($rawWeight);
            $defaultWeight = $this->snapTipWeightToMasterGrid($base);

            return [
                'staff_id' => (int) $attendance->staff_id,
                'name' => (string) $attendance->staff->name,
                'job_level' => (string) ($attendance->staff->jobLevel?->name ?? 'Unassigned'),
                'weight' => $defaultWeight,
                'amount' => 0.0,
                'is_tardy_deprived' => false,
                'is_manual_added' => false,
                'note' => null,
            ];
        })->all();
    }

    protected function recalculateRows(): void
    {
        $targetTotal = $this->normalizedTotalAmount();

        $weights = [];
        foreach ($this->rows as $row) {
            $w = (float) TipCalculationService::normalizeWeightScalar($row['weight'] ?? 0);
            $weights[] = max(0.0, $this->snapTipWeightToMasterGrid($w));
        }

        $amounts = $this->tipService()->distributeAmounts($weights, $targetTotal);

        $out = [];
        $sum = 0.0;
        foreach ($this->rows as $i => $row) {
            $w = $weights[$i] ?? 0.0;
            $amt = $amounts[$i] ?? 0.0;
            $sum += $amt;
            $out[] = array_merge($row, [
                'weight' => $w,
                'amount' => $amt,
            ]);
        }

        $this->rows = $out;
        $this->distributed_total = round($sum, 3);
        if (! $this->needsManagerPin()) {
            $this->managerPinVerified = false;
            $this->managerStaffId = null;
            $this->managerPinInput = '';
            $this->data['manager_staff_id'] = null;
            $this->data['manager_pin'] = '';
        }
    }

    /**
     * JobLevel マスタ（0–100・10 刻み）と整合するウェイトへ丸める。
     */
    protected function snapTipWeightToMasterGrid(float $value): float
    {
        $snapped = (int) round($value / 10) * 10;

        return (float) max(0, min(100, $snapped));
    }

    /**
     * 空文字の total_amount を 0 として扱い、ミリ単位に丸める。
     */
    protected function normalizedTotalAmount(): float
    {
        $rawTotal = $this->data['total_amount'] ?? '0';
        if ($rawTotal === '' || $rawTotal === null) {
            $rawTotal = '0';
        }

        $rawTotal = str_replace(',', '', (string) $rawTotal);

        return round(max(0.0, (float) $rawTotal), 3);
    }

    protected function refreshAnalytics(): void
    {
        $baseDate = Carbon::parse($this->data['business_date'] ?? BusinessDate::current()->toDateString());
        $weekStart = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $baseDate->copy()->endOfWeek(Carbon::SUNDAY);
        $monthStart = $baseDate->copy()->startOfMonth();
        $monthEnd = $baseDate->copy()->endOfMonth();

        $this->weekly_total = (float) DailyTip::query()
            ->whereBetween('business_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->sum('total_amount');

        $this->monthly_total = (float) DailyTip::query()
            ->whereBetween('business_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('total_amount');

        $recent = \App\Models\DailyTipDistribution::query()
            ->with('staff')
            ->latest('id')
            ->limit(300)
            ->get()
            ->groupBy('staff_id')
            ->map(function ($group, $staffId): array {
                $sample = $group->take(5);
                $staffName = $sample->first()?->staff?->name ?? ('#'.$staffId);

                return [
                    'staff_name' => $staffName,
                    'avg_amount' => round((float) $sample->avg('amount'), 3),
                    'count' => $sample->count(),
                ];
            })
            ->sortByDesc('avg_amount')
            ->take(10)
            ->values()
            ->all();

        $this->recent_averages = $recent;
    }

    protected function tipService(): TipCalculationService
    {
        return app(TipCalculationService::class);
    }

    public function needsManagerPin(): bool
    {
        return $this->normalizedTotalAmount() > 200.0;
    }

    /**
     * @return array<int, string>
     */
    public function managerStaffOptions(): array
    {
        return Staff::query()
            ->where('is_active', true)
            ->where('is_manager', true)
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private function verifyManagerPinOrFail(): void
    {
        $this->validate([
            'data.manager_staff_id' => ['required', 'integer', 'exists:staff,id'],
            'data.manager_pin' => ['required', 'digits:4'],
        ], attributes: [
            'data.manager_staff_id' => 'Manager',
            'data.manager_pin' => 'PIN manager',
        ]);

        $managerId = (int) ($this->data['manager_staff_id'] ?? 0);
        $key = 'tips-manager-pin:staff:'.$managerId.':'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'data.manager_pin' => 'PIN manager bloqué, attends un peu.',
            ]);
        }

        $manager = Staff::query()
            ->where('id', $managerId)
            ->where('is_active', true)
            ->where('is_manager', true)
            ->first();

        if (! $manager || ! hash_equals((string) ($manager->pin_code ?? ''), (string) ($this->data['manager_pin'] ?? ''))) {
            RateLimiter::hit($key, 300);
            throw ValidationException::withMessages([
                'data.manager_pin' => 'PIN manager invalide.',
            ]);
        }

        RateLimiter::clear($key);
        $this->managerPinVerified = true;
    }

    private function hydrateTipAmountFromFinance(): void
    {
        $businessDate = (string) ($this->data['business_date'] ?? '');
        $shift = (string) ($this->data['shift'] ?? 'lunch');

        if ($businessDate === '') {
            return;
        }

        $finance = Finance::query()
            ->whereDate('business_date', $businessDate)
            ->where('shift', $shift)
            ->where('close_status', 'success')
            ->latest('id')
            ->first();

        if (! $finance) {
            return;
        }

        $tip = (float) ($finance->final_tip_amount ?? $finance->chips ?? 0);
        $this->data['total_amount'] = (string) round(max(0, $tip), 3);
    }

    /**
     * @return \Illuminate\Support\Collection<int, Finance>
     */
    public function recentFinanceHistory()
    {
        $from = Carbon::parse($this->data['business_date'] ?? BusinessDate::current()->toDateString())
            ->subDays(2)
            ->toDateString();

        return Finance::query()
            ->whereDate('business_date', '>=', $from)
            ->orderByDesc('business_date')
            ->orderByRaw("FIELD(shift, 'dinner', 'lunch')")
            ->get();
    }
}
