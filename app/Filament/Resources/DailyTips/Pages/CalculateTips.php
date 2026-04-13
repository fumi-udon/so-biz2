<?php

namespace App\Filament\Resources\DailyTips\Pages;

use App\Filament\Resources\DailyTips\DailyTipResource;
use App\Models\Attendance;
use App\Models\DailyTip;
use App\Models\Finance;
use App\Models\Staff;
use App\Services\TipCalculationService;
use App\Support\BusinessDate;
use App\Support\DailyTipAuditContext;
use App\Support\DailyTipAuditLogger;
use App\Support\TipAttendanceScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class CalculateTips extends Page
{
    protected static string $resource = DailyTipResource::class;

    protected static string $view = 'filament.resources.daily-tips.pages.calculate-tips';

    protected static ?string $title = '🪙 Calcul de répartition';

    /** ページ標準ヘッダーを出さず、本文から入力を開始する */
    protected ?string $heading = '';

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public float $distributed_total = 0.0;

    public float $weekly_total = 0.0;

    public float $monthly_total = 0.0;

    public bool $managerPinVerified = false;

    public ?int $managerStaffId = null;

    public string $managerPinInput = '';

    /** Même jour + même service : un pourboire existe déjà en base (écrasement possible au prochain enregistrement). */
    public bool $existingTipRecord = false;

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
        $this->syncExistingTipFlag();
    }

    public function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2])
                    ->extraAttributes([
                        'class' => 'gap-y-2 gap-x-2',
                    ])
                    ->schema([
                        Section::make()
                            ->heading(null)
                            ->compact()
                            ->schema([
                                DatePicker::make('business_date')
                                    ->label('📅 Jour d’activité')
                                    ->required()
                                    ->native(false)
                                    ->locale('fr')
                                    ->displayFormat('d/m/Y')
                                    ->weekStartsOnMonday()
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->afterConditionChanged())
                                    ->helperText('Date de service pour la répartition.')
                                    ->extraInputAttributes([
                                        'class' => 'py-2 text-base text-gray-950 dark:text-white',
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => 'rounded-2xl border-2 border-b-4 border-amber-400 bg-amber-50/90 p-4 shadow-sm ring-1 ring-amber-200/80 dark:border-amber-600 dark:bg-amber-950/40 dark:ring-amber-900/50',
                            ]),
                        Section::make()
                            ->heading(null)
                            ->compact()
                            ->schema([
                                Select::make('shift')
                                    ->label('🍽️ Service')
                                    ->options([
                                        'lunch' => '☀️ Midi',
                                        'dinner' => '🌙 Soir',
                                    ])
                                    ->required()
                                    ->native(true)
                                    ->live()
                                    ->afterStateUpdated(fn () => $this->afterConditionChanged())
                                    ->extraInputAttributes([
                                        'class' => 'py-2 text-sm text-gray-950 dark:text-white',
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => 'rounded-2xl border-2 border-b-4 border-sky-400 bg-sky-50/90 p-4 shadow-sm ring-1 ring-sky-200/80 dark:border-sky-600 dark:bg-sky-950/40 dark:ring-sky-900/50',
                            ]),
                    ]),
                Placeholder::make('overwrite_notice_calculate')
                    ->label(null)
                    ->visible(fn () => $this->existingTipRecord)
                    ->content(
                        new HtmlString(
                            '<div class="flex gap-2 rounded-xl border-2 border-rose-500 bg-rose-50 p-3 text-rose-950 shadow-sm ring-1 ring-rose-200/80 dark:border-rose-600 dark:bg-rose-950/50 dark:text-rose-50 dark:ring-rose-900/50" role="status">'
                            .'<span class="shrink-0 text-lg leading-none" aria-hidden="true">⚠️</span>'
                            .'<p class="text-sm font-semibold leading-snug">'
                            .'<span class="font-black">Enregistrement existant</span> pour cette date et ce service. '
                            .'Le prochain enregistrement <span class="underline">remplacera</span> le total et toutes les répartitions.'
                            .'</p></div>'
                        )
                    )
                    ->columnSpanFull(),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('💰 Total pourboires')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.001)
                            ->required()
                            ->suffix('DT')
                            ->live(debounce: 500)
                            ->afterStateUpdated(fn () => $this->recalculateRows())
                            ->helperText('Jusqu’à 3 décimales.')
                            ->extraInputAttributes([
                                'class' => 'py-2.5 text-2xl font-black tabular-nums leading-tight text-gray-950 dark:text-white',
                                'step' => '0.001',
                                'inputmode' => 'decimal',
                            ]),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'rounded-2xl border-2 border-b-4 border-emerald-500 bg-emerald-50/90 p-4 shadow-sm ring-1 ring-emerald-200/80 dark:border-emerald-600 dark:bg-emerald-950/40 dark:ring-emerald-900/50',
                    ]),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->visible(fn () => $this->needsManagerPin())
                    ->schema([
                        Select::make('manager_staff_id')
                            ->label('👤 Responsable (validation) > 200 DT ?')
                            ->options(fn (): array => $this->managerStaffOptions())
                            ->placeholder('Choisir un responsable')
                            ->native(true)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->managerStaffId = $state === '' || $state === null ? null : (int) $state;
                                $this->managerPinVerified = false;
                            }),
                        TextInput::make('manager_pin')
                            ->label('🔐 Code PIN responsable')
                            ->password()
                            ->maxLength(4)
                            ->live(debounce: 500)
                            ->afterStateUpdated(function (mixed $state): void {
                                $this->managerPinInput = (string) ($state ?? '');
                                $this->managerPinVerified = false;
                            }),
                    ])
                    ->extraAttributes([
                        'class' => 'rounded-2xl border-2 border-b-4 border-rose-400 bg-rose-50/90 p-4 shadow-sm ring-1 ring-rose-200/80 dark:border-rose-600 dark:bg-rose-950/40 dark:ring-rose-900/50',
                    ]),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->schema([
                        Hidden::make('selected_staff_id'),
                        Placeholder::make('pointages_notice')
                            ->label(null)
                            ->content(
                                new HtmlString(
                                    '<p class="text-xs font-semibold leading-snug text-gray-950 dark:text-white">'
                                    .'チップ付与は出勤簿(Pointages)と連動してます。 '
                                    .'<span class="font-black text-sky-800 dark:text-sky-200">Pointages</span>.'
                                    .'</p>'
                                )
                            ),
                    ])
                    ->extraAttributes([
                        'class' => 'rounded-2xl border-2 border-b-4 border-sky-400 bg-sky-50/90 p-3 shadow-sm ring-1 ring-sky-200/80 dark:border-sky-600 dark:bg-sky-950/40 dark:ring-sky-900/50',
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function afterConditionChanged(): void
    {
        $this->loadRows();
        $this->hydrateTipAmountFromFinance();
        $this->recalculateRows();
        $this->refreshAnalytics();
        $this->syncExistingTipFlag();
    }

    /**
     * Indique si un {@see DailyTip} existe déjà pour la date et le service courants (clé métier unique).
     */
    protected function syncExistingTipFlag(): void
    {
        $rawDate = $this->data['business_date'] ?? null;
        $shift = (string) ($this->data['shift'] ?? '');

        if ($rawDate === null || $rawDate === '') {
            $this->existingTipRecord = false;

            return;
        }

        if (! in_array($shift, ['lunch', 'dinner'], true)) {
            $this->existingTipRecord = false;

            return;
        }

        try {
            $businessDate = Carbon::parse($rawDate)->toDateString();
        } catch (\Throwable) {
            $this->existingTipRecord = false;

            return;
        }

        $this->existingTipRecord = DailyTip::query()
            ->whereDate('business_date', $businessDate)
            ->where('shift', $shift)
            ->exists();
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
            'data.business_date' => 'jour d’activité',
            'data.shift' => 'service',
            'data.total_amount' => 'total pourboires',
        ]);

        if (empty($this->rows)) {
            Notification::make()
                ->warning()
                ->title('Aucun membre à valider')
                ->body("Aucun membre présent pour ce service. Enregistrez d'abord les présences dans l'écran Pointages.")
                ->send();

            return;
        }

        $savedTotal = $this->normalizedTotalAmount();

        if ($this->needsManagerPin()) {
            $this->verifyManagerPinOrFail();
        }

        $businessDateRaw = $this->data['business_date'] ?? null;
        $shift = $this->data['shift'] ?? 'lunch';
        $businessDate = Carbon::parse($businessDateRaw)->toDateString();

        $lockKey = "daily_tip:confirm:{$businessDate}:{$shift}";
        $lock = Cache::lock($lockKey, 10);

        if (! $lock->get()) {
            Notification::make()
                ->warning()
                ->title('Traitement en cours')
                ->body('Un autre appareil valide déjà cette date et ce service. Réessayez dans quelques secondes.')
                ->send();

            return;
        }

        try {
            $flagField = $shift === 'lunch' ? 'is_lunch_tip_applied' : 'is_dinner_tip_applied';
            $denyField = $shift === 'lunch' ? 'is_lunch_tip_denied' : 'is_dinner_tip_denied';
            $rowStaffIds = array_map(fn (array $r): int => (int) $r['staff_id'], $this->rows);

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
                            $flagField => true,
                            $denyField => false,
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
        } finally {
            $lock->release();
        }

        Notification::make()
            ->title('Répartition enregistrée')
            ->success()
            ->send();

        $url = DailyTipResource::getUrl('index');
        $this->redirect($url, navigate: FilamentView::hasSpaMode($url));
    }

    protected function loadRows(): void
    {
        $rawDate = $this->data['business_date'] ?? null;
        $businessDate = ($rawDate !== null && $rawDate !== '')
            ? Carbon::parse($rawDate)->toDateString()
            : BusinessDate::current()->toDateString();
        $shift = $this->data['shift'] ?? 'lunch';

        $attendances = TipAttendanceScope::applyGoldenFormula(
            Attendance::query()->whereDate('date', $businessDate),
            $shift === 'dinner' ? 'dinner' : 'lunch',
        )
            ->whereHas('staff', fn ($q) => $q->withTrashed())
            ->with(['staff' => fn ($query) => $query->withTrashed()->with('jobLevel')])
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
            'data.manager_staff_id' => 'responsable',
            'data.manager_pin' => 'code PIN',
        ]);

        $managerId = (int) ($this->data['manager_staff_id'] ?? 0);
        $key = 'tips-manager-pin:staff:'.$managerId.':'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'data.manager_pin' => 'Trop de tentatives PIN. Réessayez dans quelques minutes.',
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
                'data.manager_pin' => 'Code PIN incorrect.',
            ]);
        }

        RateLimiter::clear($key);
        $this->managerPinVerified = true;
    }

    private function hydrateTipAmountFromFinance(): void
    {
        $rawDate = $this->data['business_date'] ?? null;
        $shift = (string) ($this->data['shift'] ?? '');

        if ($rawDate === null || $rawDate === '') {
            return;
        }

        if (! in_array($shift, ['lunch', 'dinner'], true)) {
            return;
        }

        $businessDate = Carbon::parse($rawDate)->toDateString();

        $finance = Finance::query()
            ->whereDate('business_date', $businessDate)
            ->where('shift', $shift)
            ->where('close_status', 'success')
            ->latest('id')
            ->first();

        if ($finance === null) {
            $this->data['total_amount'] = '0';

            return;
        }

        $chips = (float) ($finance->chips ?? 0);
        $this->data['total_amount'] = (string) round(max(0, $chips), 3);
    }

    /**
     * @return Collection<int, Finance>
     */
    public function recentFinanceHistory()
    {
        $rawDate = $this->data['business_date'] ?? null;
        $baseDate = ($rawDate !== null && $rawDate !== '')
            ? Carbon::parse($rawDate)->toDateString()
            : BusinessDate::current()->toDateString();
        $from = Carbon::parse($baseDate)->subDays(2)->toDateString();

        return Finance::query()
            ->whereDate('business_date', '>=', $from)
            ->orderByDesc('business_date')
            ->orderByRaw("CASE shift WHEN 'dinner' THEN 0 WHEN 'lunch' THEN 1 ELSE 2 END")
            ->get();
    }
}
