<?php

namespace App\Livewire;

use App\Models\Attendance;
use App\Models\Staff;
use App\Services\TimecardPinValidator;
use App\Services\TimecardPunchOutcome;
use App\Services\TimecardPunchService;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
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

    public function mount(): void
    {
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
            'selectedStaffId' => 'スタッフ',
            'pinCode' => 'PIN',
        ]);

        $staff = Staff::query()
            ->where('id', $this->selectedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->addError('selectedStaffId', 'スタッフが見つかりません。');

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
            $this->bannerError = 'この操作は現在できません。';

            return;
        }

        $staff = Staff::query()
            ->where('id', $this->authenticatedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->resetToStepOne();
            $this->bannerError = 'セッションが無効です。最初からやり直してください。';

            return;
        }

        $outcome = app(TimecardPunchService::class)->processNormalPunch($staff, $action);

        if (! $outcome->ok) {
            $this->bannerError = $outcome->errorMessage;

            return;
        }

        $this->applySuccessOutcome($outcome);
        $this->refreshShiftState($staff);
        $this->syncExtraMealDefault();
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
            $this->bannerError = '臨時出勤は現在できません。';

            return;
        }

        $this->validate([
            'extraMeal' => ['required', Rule::in($allowed)],
            'extraReason' => ['nullable', 'string', 'max:500'],
        ], [], [
            'extraMeal' => 'シフト',
        ]);

        $staff = Staff::query()
            ->where('id', $this->authenticatedStaffId)
            ->where('is_active', true)
            ->first();

        if (! $staff) {
            $this->resetToStepOne();
            $this->bannerError = 'セッションが無効です。最初からやり直してください。';

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

        $this->applySuccessOutcome($outcome);
        $this->extraReason = '';
        $this->refreshShiftState($staff);
        $this->syncExtraMealDefault();
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

    private function applySuccessOutcome(TimecardPunchOutcome $outcome): void
    {
        if ($outcome->postFlow === 'mypage_late' && $outcome->lateMinutes !== null) {
            $this->bannerSuccess = '打刻しました（遅刻 '.$outcome->lateMinutes.' 分として記録）';
        } else {
            $this->bannerSuccess = '打刻が完了しました。お疲れ様です。';
        }

        $this->js('setTimeout(() => $wire.resetAfterPunch(), 3500)');
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
    }
}
