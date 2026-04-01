<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Staff;
use App\Services\TimecardPinValidator;
use App\Services\TimecardPunchOutcome;
use App\Services\TimecardPunchService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.timecard')]
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

    public function mount(): void
    {
        request()->session()->forget('mypage_staff_id');

        $this->staffOptions = Staff::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Staff $s): array => ['id' => $s->id, 'name' => $s->name])
            ->values()
            ->all();
    }

    public function authenticate(): void
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

            return;
        }

        $pinError = app(TimecardPinValidator::class)->validate($staff, $this->pinCode);

        if ($pinError !== null) {
            $this->addError('pinCode', $pinError);

            return;
        }

        $this->authenticatedStaffId = $staff->id;
        $this->authenticatedStaffName = $staff->name;
        $this->pinCode = '';
        $this->refreshShiftState($staff);
        $this->syncExtraMealDefault();
        $this->step = 2;
    }

    public function punch(string $action): void
    {
        $this->bannerError = null;
        $this->bannerSuccess = null;

        if ($this->step !== 2 || $this->authenticatedStaffId === null) {
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
            $this->openTipResultModal($action, $isLate);

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
        $this->openTipResultModal($action, false);
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
        $this->dispatch('close-modal', id: 'punch-complete-modal');
        $this->showPunchCompleteModal = false;
        $this->punchCompleteLabel = null;
        $this->resetAfterPunch();
    }

    public function applyForTip(): mixed
    {
        if ($this->authenticatedStaffId === null || $this->tipTargetShift === null) {
            return redirect()->route('mypage.index');
        }

        $dateString = app(TimecardPunchService::class)->resolveTargetBusinessDate()->toDateString();
        $attendance = Attendance::query()
            ->where('staff_id', $this->authenticatedStaffId)
            ->where('date', $dateString)
            ->first();

        if ($attendance) {
            $table = $attendance->getTable();
            if ($this->tipTargetShift === 'lunch') {
                if (! Schema::hasColumn($table, 'is_lunch_tip_applied')) {
                    return redirect()
                        ->route('mypage.index', ['staff_id' => $this->authenticatedStaffId])
                        ->with('error', 'La colonne de demande de tip est absente. Demandez la migration a un admin.');
                }
                $attendance->is_lunch_tip_applied = true;
            } elseif ($this->tipTargetShift === 'dinner') {
                if (! Schema::hasColumn($table, 'is_dinner_tip_applied')) {
                    return redirect()
                        ->route('mypage.index', ['staff_id' => $this->authenticatedStaffId])
                        ->with('error', 'La colonne de demande de tip est absente. Demandez la migration a un admin.');
                }
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

        return view('livewire.timecard-form', [
            'targetBusinessDate' => $targetDate,
        ]);
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
        $this->dispatch('open-modal', id: 'punch-complete-modal');
        $this->js('setTimeout(() => $wire.closePunchCompleteModal(), 4200)');
    }

    private function openTipResultModal(string $action, bool $isLate): void
    {
        $this->tipTargetShift = $action === 'lunch_in' ? 'lunch' : 'dinner';
        $this->tipModalState = $isLate ? 'LOSE' : 'WIN';
        $this->showTipModal = true;
        $this->dispatch('open-modal', id: 'tip-result-modal');
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
        $this->resetTipModalState();
    }
}
