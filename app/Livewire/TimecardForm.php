<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Staff;
use App\Services\StaffPinAuthenticationService;
use App\Services\TimecardPunchOutcome;
use App\Services\TimecardPunchService;
use App\Services\TipCalculationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Component;

class TimecardForm extends Component
{
    public int $step = 1;

    /** @var list<array{id: int, name: string}> */
    public array $staffOptions = [];

    public ?int $selectedStaffId = null;

    public string $pinCode = '';

    public ?int $authenticatedStaffId = null;

    public string $authenticatedStaffName = '';

    /**
     * @var array{lunch_scheduled: bool, dinner_scheduled: bool, lunch_in: bool, lunch_out: bool, dinner_in: bool, dinner_out: bool}
     */
    public array $shiftState = [
        'lunch_scheduled' => false,
        'dinner_scheduled' => false,
        'lunch_in' => false,
        'lunch_out' => false,
        'dinner_in' => false,
        'dinner_out' => false,
    ];

    public ?string $bannerSuccess = null;

    public ?string $bannerError = null;

    public string $extraMeal = 'lunch';

    public string $extraReason = '';

    public bool $showTipModal = false;

    public ?string $tipModalState = null;

    public ?string $tipTargetShift = null;

    public bool $showPunchCompleteModal = false;

    public ?string $punchCompleteLabel = null;

    /** Aujourd'hui: aucun creneau dejeuner/diner prevu dans fixed_shifts (hors pointage en cours). */
    public bool $noWorkDataToday = false;

    public function mount(): void
    {
        $agent = request()->header('User-Agent', '');

        // 本番環境 ＆ Operaブラウザ系(OPR, OPT, Opera)以外 を弾く
        if (app()->environment('production') && ! str_contains($agent, 'OPR') && ! str_contains($agent, 'OPT') && ! str_contains($agent, 'Opera')) {
            abort(403, 'Pointage impossible depuis cet appareil. Veuillez utiliser la tablette dédiée du restaurant.');
        }

        request()->session()->forget('mypage_staff_id');

        $this->staffOptions = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $s): array => ['id' => $s->id, 'name' => $s->name])
            ->values()
            ->all();
    }

    public function authenticate(): mixed
    {
        $this->bannerError = null;
        $this->bannerSuccess = null;

        $this->validate([
            'selectedStaffId' => ['required', 'integer', 'exists:staff,id'],
            'pinCode' => ['required', 'string', 'digits:4'],
        ], [], [
            'selectedStaffId' => 'Personnel',
            'pinCode' => 'PIN',
        ]);

        $staff = Staff::query()
            ->where('id', $this->selectedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->addError('selectedStaffId', 'Personnel introuvable.');

            return null;
        }

        $pinError = app(StaffPinAuthenticationService::class)->verify($staff, $this->pinCode, 'timecard', 5, 60);

        if ($pinError !== null) {
            $this->addError('pinCode', $pinError);

            return null;
        }

        $this->pinCode = '';
        $this->refreshShiftState($staff);

        $s = $this->shiftState;
        $noScheduleToday = ! $s['lunch_scheduled'] && ! $s['dinner_scheduled'];
        $openLunch = $s['lunch_in'] && ! $s['lunch_out'];
        $openDinner = $s['dinner_in'] && ! $s['dinner_out'];

        $this->noWorkDataToday = $noScheduleToday && ! $openLunch && ! $openDinner;

        $this->authenticatedStaffId = $staff->id;
        $this->authenticatedStaffName = $staff->name;
        $this->syncExtraMealDefault();
        $this->step = 2;

        return null;
    }

    public function punch(string $action): void
    {
        $this->bannerError = null;
        $this->bannerSuccess = null;

        if ($this->step !== 2 || $this->authenticatedStaffId === null) {
            return;
        }

        if ($this->noWorkDataToday) {
            return;
        }

        if (! in_array($action, ['lunch_in', 'lunch_out', 'dinner_in', 'dinner_out'], true)) {
            return;
        }

        if ($this->isPunchDisabled($action)) {
            $this->bannerError = 'Cette operation est actuellement indisponible.';

            return;
        }

        $staff = Staff::query()
            ->with('jobLevel')
            ->where('id', $this->authenticatedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->resetToStepOne();
            $this->bannerError = 'Session invalide. Recommencez depuis le debut.';

            return;
        }

        $outcome = app(TimecardPunchService::class)->processNormalPunch($staff, $action);

        if (! $outcome->ok) {
            $this->bannerError = $outcome->errorMessage;

            return;
        }

        $this->refreshShiftState($staff);
        $this->syncExtraMealDefault();

        if (in_array($action, ['lunch_in', 'dinner_in'], true)) {
            $isLate = $outcome->postFlow === 'mypage_late' && ($outcome->lateMinutes ?? 0) > 0;
            $isZeroWeight = TipCalculationService::normalizeWeightScalar($staff->jobLevel?->default_weight) <= 0.0;
            $this->openTipResultModal($action, $isLate, $isZeroWeight);

            return;
        }

        $this->applySuccessOutcome($outcome, $action);
    }

    public function submitExtraShift(): void
    {
        $this->bannerError = null;
        $this->bannerSuccess = null;

        if ($this->step !== 2 || $this->authenticatedStaffId === null) {
            return;
        }

        if ($this->noWorkDataToday) {
            return;
        }

        $canLunch = ! $this->shiftState['lunch_scheduled'] && ! $this->shiftState['lunch_in'];
        $canDinner = ! $this->shiftState['dinner_scheduled'] && ! $this->shiftState['dinner_in'];

        $allowed = array_values(array_filter([
            $canLunch ? 'lunch' : null,
            $canDinner ? 'dinner' : null,
        ]));

        if ($allowed === []) {
            $this->bannerError = 'L\'entree exceptionnelle n\'est pas disponible maintenant.';

            return;
        }

        $this->validate([
            'extraMeal' => ['required', Rule::in($allowed)],
            'extraReason' => ['nullable', 'string', 'max:500'],
        ], [], [
            'extraMeal' => 'Shift',
        ]);

        $staff = Staff::query()
            ->with('jobLevel')
            ->where('id', $this->authenticatedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->resetToStepOne();
            $this->bannerError = 'Session invalide. Recommencez depuis le debut.';

            return;
        }

        $outcome = app(TimecardPunchService::class)->processExtraShift(
            $staff,
            $this->extraMeal,
            $this->extraReason
        );

        if (! $outcome->ok) {
            $this->bannerError = $outcome->errorMessage;

            return;
        }

        $this->extraReason = '';
        $this->refreshShiftState($staff);
        $this->syncExtraMealDefault();

        $action = $this->extraMeal === 'lunch' ? 'lunch_in' : 'dinner_in';
        $isZeroWeight = TipCalculationService::normalizeWeightScalar($staff->jobLevel?->default_weight) <= 0.0;
        $this->openTipResultModal($action, false, $isZeroWeight);
    }

    public function resetAfterPunch(): void
    {
        $this->bannerSuccess = null;
        $this->resetToStepOne();
    }

    public function backToAuth(): void
    {
        $this->bannerError = null;
        $this->bannerSuccess = null;
        $this->resetToStepOne();
    }

    public function closePunchCompleteModal(): void
    {
        $this->showPunchCompleteModal = false;
        $this->punchCompleteLabel = null;
        $this->resetAfterPunch();
    }

    public function applyForTip(): mixed
    {
        if ($this->authenticatedStaffId === null || $this->tipTargetShift === null) {
            return redirect()->route('mypage.index');
        }

        // weight = 0 のスタッフは自己申請不可（Manager 手動付与経路のみ許可）
        $staffForWeight = Staff::query()
            ->with('jobLevel')
            ->where('id', $this->authenticatedStaffId)
            ->where('is_active', true)
            ->first();

        if (TipCalculationService::normalizeWeightScalar($staffForWeight?->jobLevel?->default_weight) <= 0.0) {
            session()->put('mypage_staff_id', $this->authenticatedStaffId);
            $this->resetTipModalState();

            return redirect()->route('mypage.index', ['staff_id' => $this->authenticatedStaffId]);
        }

        $dateString = app(TimecardPunchService::class)->resolveTargetBusinessDate()->toDateString();
        $attendance = Attendance::query()
            ->where('staff_id', $this->authenticatedStaffId)
            ->where('date', $dateString)
            ->first();

        if ($attendance) {
            if ($this->tipTargetShift === 'lunch') {
                $attendance->is_lunch_tip_applied = true;
            } elseif ($this->tipTargetShift === 'dinner') {
                $attendance->is_dinner_tip_applied = true;
            }
            $attendance->save();
        }

        session()->put('mypage_staff_id', $this->authenticatedStaffId);
        $this->resetTipModalState();

        return redirect()->route('mypage.index', ['staff_id' => $this->authenticatedStaffId]);
    }

    public function declineTipAndRedirect(): mixed
    {
        if ($this->authenticatedStaffId === null) {
            $this->resetTipModalState();

            return redirect()->route('mypage.index');
        }

        session()->put('mypage_staff_id', $this->authenticatedStaffId);
        $this->resetTipModalState();

        return redirect()->route('mypage.index', ['staff_id' => $this->authenticatedStaffId]);
    }

    public function isPunchDisabled(string $action): bool
    {
        if ($this->step !== 2) {
            return true;
        }

        $s = $this->shiftState;

        return match ($action) {
            'lunch_in' => ! ($s['lunch_scheduled'] && ! $s['lunch_in'] && ! $s['lunch_out']),
            'lunch_out' => ! ($s['lunch_in'] && ! $s['lunch_out']),
            'dinner_in' => ! ($s['dinner_scheduled'] && ! $s['dinner_in'] && ! $s['dinner_out']),
            'dinner_out' => ! ($s['dinner_in'] && ! $s['dinner_out']),
            default => true,
        };
    }

    public function allMainPunchesDisabled(): bool
    {
        if ($this->step !== 2) {
            return true;
        }

        $s = $this->shiftState;

        return ! $s['lunch_scheduled'] && ! $s['dinner_scheduled'];
    }

    public function render(): View
    {
        $targetDate = app(TimecardPunchService::class)->resolveTargetBusinessDate();
        $weeklyMissionRows = [];
        $todayAttendance = null;

        if ($this->authenticatedStaffId !== null) {
            $weeklyMissionRows = $this->buildWeeklyMissionRows($this->authenticatedStaffId);
            $todayAttendance = Attendance::query()
                ->where('staff_id', $this->authenticatedStaffId)
                ->where('date', $targetDate->toDateString())
                ->first();
        }

        // hasShiftToday: poste prevu aujourd'hui (inverse de noWorkDataToday). La ligne Attendance peut n'exister qu'apres le premier pointage.
        return view('livewire.timecard-form', [
            'targetBusinessDate' => $targetDate,
            'weeklyMissionRows' => $weeklyMissionRows,
            'attendance' => $todayAttendance,
            'hasShiftToday' => $this->authenticatedStaffId !== null && ! $this->noWorkDataToday,
        ])->layout('layouts.app', [
            'layoutTitle' => 'Pointage — '.config('app.name'),
        ]);
    }

    /**
     * Semaine du lundi au dimanche (now()): pointages enregistres dans `attendances`.
     *
     * @return list<array{label_fr: string, date_label: string, is_today: bool, lunch: string, dinner: string, scheduled_in: string}> scheduled_in: L/D 予定スナップショット
     */
    private function buildWeeklyMissionRows(int $staffId): array
    {
        $weekStart = now()->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = now()->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

        $attendances = Attendance::query()
            ->where('staff_id', $staffId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('date')
            ->get()
            ->keyBy(fn (Attendance $a): string => $a->date->toDateString());

        $businessToday = app(TimecardPunchService::class)->resolveTargetBusinessDate()->startOfDay();
        $labelsFr = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
        $rows = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $ds = $day->toDateString();
            $att = $attendances->get($ds);
            $rows[] = [
                'label_fr' => $labelsFr[$i],
                'date_label' => $day->format('d/m'),
                'is_today' => $day->isSameDay($businessToday),
                'lunch' => $this->formatAttendanceMealRange($att, 'lunch'),
                'dinner' => $this->formatAttendanceMealRange($att, 'dinner'),
                'scheduled_in' => $this->formatScheduledSnapshotsForRow($att),
            ];
        }

        return $rows;
    }

    private function formatScheduledSnapshotsForRow(?Attendance $att): string
    {
        if ($att === null) {
            return '—';
        }

        $parts = [];
        if ($att->scheduled_in_at !== null) {
            $parts[] = 'L '.$att->scheduled_in_at->format('H:i');
        }
        if ($att->scheduled_dinner_at !== null) {
            $parts[] = 'D '.$att->scheduled_dinner_at->format('H:i');
        }

        return $parts === [] ? '—' : implode(' · ', $parts);
    }

    /**
     * @param  'lunch'|'dinner'  $meal
     */
    private function formatAttendanceMealRange(?Attendance $att, string $meal): string
    {
        if ($att === null) {
            return '—';
        }

        return match ($meal) {
            'lunch' => $this->formatAttendanceClockPair($att->lunch_in_at, $att->lunch_out_at),
            'dinner' => $this->formatAttendanceClockPair($att->dinner_in_at, $att->dinner_out_at),
            default => '—',
        };
    }

    private function formatAttendanceClockPair(?Carbon $in, ?Carbon $out): string
    {
        if ($in === null && $out === null) {
            return '—';
        }

        if ($in !== null && $out !== null) {
            return $in->format('H:i').'–'.$out->format('H:i');
        }

        if ($in !== null) {
            return 'IN '.$in->format('H:i');
        }

        return '—';
    }

    private function refreshShiftState(Staff $staff): void
    {
        $dateString = app(TimecardPunchService::class)->resolveTargetBusinessDate()->toDateString();
        $svc = app(TimecardPunchService::class);

        $att = Attendance::query()
            ->where('staff_id', $staff->id)
            ->where('date', $dateString)
            ->first();

        $bd = $svc->resolveTargetBusinessDate();

        $this->shiftState = [
            'lunch_scheduled' => $svc->isMealScheduled($staff, $bd, 'lunch'),
            'dinner_scheduled' => $svc->isMealScheduled($staff, $bd, 'dinner'),
            'lunch_in' => $att !== null && $att->lunch_in_at !== null,
            'lunch_out' => $att !== null && $att->lunch_out_at !== null,
            'dinner_in' => $att !== null && $att->dinner_in_at !== null,
            'dinner_out' => $att !== null && $att->dinner_out_at !== null,
        ];
    }

    private function syncExtraMealDefault(): void
    {
        $s = $this->shiftState;
        $canLunch = ! $s['lunch_scheduled'] && ! $s['lunch_in'];
        $canDinner = ! $s['dinner_scheduled'] && ! $s['dinner_in'];

        if ($canLunch && ! $canDinner) {
            $this->extraMeal = 'lunch';
        } elseif (! $canLunch && $canDinner) {
            $this->extraMeal = 'dinner';
        }
    }

    private function applySuccessOutcome(TimecardPunchOutcome $outcome, string $action): void
    {
        $this->punchCompleteLabel = match ($action) {
            'lunch_out' => 'LUNCH OUT',
            'dinner_out' => 'DINNER OUT',
            default => 'SHIFT OUT',
        };

        if ($outcome->postFlow === 'mypage_late' && $outcome->lateMinutes !== null) {
            $this->bannerSuccess = 'Sortie enregistree (retard de '.$outcome->lateMinutes.' min).';
        } else {
            $this->bannerSuccess = null;
        }
        $this->showPunchCompleteModal = true;
        $this->js('setTimeout(() => $wire.closePunchCompleteModal(), 4200)');
    }

    private function openTipResultModal(string $action, bool $isLate, bool $isZeroWeight = false): void
    {
        $this->tipTargetShift = $action === 'lunch_in' ? 'lunch' : 'dinner';
        $this->tipModalState = $isZeroWeight ? 'SKIP' : ($isLate ? 'LOSE' : 'WIN');
        $this->showTipModal = true;
    }

    private function resetTipModalState(): void
    {
        $this->showTipModal = false;
        $this->tipModalState = null;
        $this->tipTargetShift = null;
    }

    private function resetToStepOne(): void
    {
        $this->step = 1;
        $this->authenticatedStaffId = null;
        $this->authenticatedStaffName = '';
        $this->selectedStaffId = null;
        $this->pinCode = '';
        $this->shiftState = [
            'lunch_scheduled' => false,
            'dinner_scheduled' => false,
            'lunch_in' => false,
            'lunch_out' => false,
            'dinner_in' => false,
            'dinner_out' => false,
        ];
        $this->extraMeal = 'lunch';
        $this->extraReason = '';
        $this->showPunchCompleteModal = false;
        $this->punchCompleteLabel = null;
        $this->noWorkDataToday = false;
        $this->resetTipModalState();
    }
}
