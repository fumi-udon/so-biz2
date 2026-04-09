<?php

namespace App\Livewire;

use App\Actions\Finance\PersistDailyCloseResultAction;
use App\Models\Finance;
use App\Models\Setting;
use App\Models\Staff;
use App\Services\BistronipponOrdersRecettesService;
use App\Services\TimecardPinValidator;
use App\Support\BusinessDate;
use App\Support\CaisseMoneyInputNormalizer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.daily-close')]
class FrontendDailyClose extends Component
{
    private const ERR_RECETTES_LUNCH_BEFORE_15H = 'Les données seront synchronisées après 15h00. Réessayez plus tard.';

    private const ERR_RECETTES_DINNER_BEFORE_2210 = 'Les données seront synchronisées après 22h00. Réessayez plus tard.';

    private const ERR_RECETTES_API_GENERIC = 'Impossible de récupérer les ventes. Vérifiez la connexion ou réessayez plus tard.';

    /** @var array<string, mixed> */
    public array $data = [];

    public string $businessDateStr = '';

    public bool $closeSessionReady = false;

    public ?int $responsibleStaffId = null;

    public ?string $gateShift = null;

    public ?int $gateStaffId = null;

    public string $gatePinInput = '';

    /** セッションゲートを全画面オーバーレイで表示 */
    public bool $showSessionGate = true;

    private float $fondDeCaisseValue = 100.000;

    private float $toleranceMoinsValue = 1.000;

    private float $tolerancePlusValue = 3.000;

    public ?string $recettesApiErrorMessage = null;

    /**
     * @var array{date: string, lunch: float, dinner: float, journal: float}|null
     */
    public ?array $fetchedRecettesPanel = null;

    private ?string $priorBusinessDateForVentesSoir = null;

    public string $bannerError = '';

    public string $bannerSuccess = '';

    /** bravo | retry */
    public string $resultModalKind = 'bravo';

    /** @var array<string, mixed> */
    public array $resultModalCalc = [];

    /** @var array<string, mixed> */
    public array $resultModalPayload = [];

    public string $resultModalShiftLabel = '';

    public string $resultModalHint = '';

    public bool $resultModalDbSaved = false;

    public bool $showResultOverlay = false;

    public bool $historyDetailOpen = false;

    public function mount(): void
    {
        $this->hydrateCaisseConfig();

        $this->businessDateStr = BusinessDate::current()->toDateString();
        $this->closeSessionReady = false;
        $this->responsibleStaffId = null;
        $this->gateShift = null;
        $this->gateStaffId = null;
        $this->gatePinInput = '';
        $this->showSessionGate = true;

        $this->data = [
            'business_date' => $this->businessDateStr,
            'shift' => 'dinner',
            'lunch_recettes' => null,
            'lunch_montant_initial' => $this->fondDeCaisseValue,
            'lunch_cash' => 0,
            'lunch_cheque' => 0,
            'lunch_carte' => 0,
            'lunch_chips' => 0,
            'dinner_recettes' => 0,
            'dinner_montant_initial' => $this->fondDeCaisseValue,
            'dinner_cash' => 0,
            'dinner_cheque' => 0,
            'dinner_carte' => 0,
            'dinner_chips' => 0,
        ];

        $this->syncPriorBusinessDateForVentesSoir();
    }

    public function updatedDataBusinessDate(mixed $value): void
    {
        if (is_string($value)) {
            $this->handleBusinessDateChange($value);
        }
    }

    public function updatedDataLunchRecettes(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('lunch_recettes', $value);
    }

    public function updatedDataLunchChips(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('lunch_chips', $value);
    }

    public function updatedDataLunchCash(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('lunch_cash', $value);
    }

    public function updatedDataLunchCheque(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('lunch_cheque', $value);
    }

    public function updatedDataLunchCarte(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('lunch_carte', $value);
    }

    public function updatedDataDinnerRecettes(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('dinner_recettes', $value);
    }

    public function updatedDataDinnerChips(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('dinner_chips', $value);
    }

    public function updatedDataDinnerCash(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('dinner_cash', $value);
    }

    public function updatedDataDinnerCheque(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('dinner_cheque', $value);
    }

    public function updatedDataDinnerCarte(mixed $value): void
    {
        $this->syncNormalizedCaisseMoney('dinner_carte', $value);
    }

    public function updatedGatePinInput(mixed $value): void
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';
        $digits = substr($digits, 0, 4);
        if ($digits !== (string) $value) {
            $this->gatePinInput = $digits;
        }
    }

    private function syncNormalizedCaisseMoney(string $key, mixed $value): void
    {
        $normalized = CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal($value);
        if (($this->data[$key] ?? null) !== $normalized) {
            $this->data[$key] = $normalized;
        }
    }

    private function handleBusinessDateChange(string $value): void
    {
        if ($value === '') {
            return;
        }

        $this->businessDateStr = $value;
        $this->recettesApiErrorMessage = null;

        if ($this->priorBusinessDateForVentesSoir !== null && $value !== $this->priorBusinessDateForVentesSoir) {
            $this->data['lunch_recettes'] = null;
            $this->fetchedRecettesPanel = null;
        }

        $this->priorBusinessDateForVentesSoir = $value;
    }

    /**
     * @return array<int, string>
     */
    public function staffOptions(): array
    {
        return Staff::query()
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->whereHas(
                'jobLevel',
                fn ($query) => $query->where('level', '>', 3),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function confirmCloseSessionGate(): void
    {
        $this->resetErrorBag();
        $this->bannerError = '';
        $this->bannerSuccess = '';

        $this->validate([
            'gateShift' => 'required|in:lunch,dinner',
            'gateStaffId' => 'required|integer|exists:staff,id',
            'gatePinInput' => 'required|string|digits:4',
        ], [
            'gateShift.required' => 'Choisissez Midi ou Soir.',
            'gateStaffId.required' => 'Choisissez un responsable.',
            'gatePinInput.required' => 'Saisissez le code PIN (4 chiffres).',
            'gatePinInput.digits' => 'Code PIN : 4 chiffres.',
        ], [
            'gateShift' => 'Service',
            'gateStaffId' => 'Responsable',
            'gatePinInput' => 'Code PIN',
        ]);

        $staffId = (int) $this->gateStaffId;

        $staff = Staff::query()
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->whereHas(
                'jobLevel',
                fn ($query) => $query->where('level', '>', 3),
            )
            ->find($staffId);

        if ($staff === null) {
            $this->addError('gateStaffId', 'Responsable invalide ou niveau insuffisant (niveau > 3 requis).');

            return;
        }

        $pinError = app(TimecardPinValidator::class)->validate($staff, $this->gatePinInput);
        if ($pinError !== null) {
            $this->addError('gatePinInput', $pinError);
            $this->gatePinInput = '';

            return;
        }

        $this->responsibleStaffId = $staff->id;
        $this->gatePinInput = '';
        $this->gateStaffId = null;

        $this->data['shift'] = $this->gateShift;
        $this->syncPriorBusinessDateForVentesSoir();

        $this->closeSessionReady = true;
        $this->fetchedRecettesPanel = null;
        $this->showSessionGate = false;

        $this->bannerSuccess = 'Responsable confirmé. Vous pouvez continuer la saisie.';
    }

    public function reopenSessionGate(): void
    {
        $this->closeSessionReady = false;
        $this->responsibleStaffId = null;
        $this->gatePinInput = '';
        $this->gateStaffId = null;
        $this->gateShift = null;
        $this->fetchedRecettesPanel = null;
        $this->showSessionGate = true;
        $this->bannerError = '';
        $this->bannerSuccess = '';
    }

    public function closeGateAndGoHome(): void
    {
        $this->redirect(route('home'));
    }

    public function fetchRecettesFromApi(): void
    {
        $this->recettesApiErrorMessage = null;

        $date = (string) ($this->data['business_date'] ?? $this->businessDateStr ?? '');
        if ($date === '') {
            $this->recettesApiErrorMessage = 'Sélectionnez une date.';

            return;
        }

        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $scheduleError = $this->recettesFetchBlockedBySchedule($date, $shift);
        if ($scheduleError !== null) {
            $this->recettesApiErrorMessage = $scheduleError;

            return;
        }

        $key = 'daily-close-fetch-recettes:0:'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->recettesApiErrorMessage = 'Trop de requêtes. Réessayez dans une minute.';

            return;
        }

        RateLimiter::hit($key, 60);

        try {
            /** @var BistronipponOrdersRecettesService $svc */
            $svc = app(BistronipponOrdersRecettesService::class);
            $totals = $svc->fetchLunchDinnerTotals($date);

            $this->fetchedRecettesPanel = [
                'date' => $date,
                'lunch' => (float) $totals['lunch'],
                'dinner' => (float) $totals['dinner'],
                'journal' => (float) $totals['journal'],
            ];
            $this->applyFetchedRecettesToVentesPosField();
        } catch (Throwable $e) {
            Log::warning('daily_close.fetch_recettes_failed', ['exception' => $e]);
            $this->recettesApiErrorMessage = self::ERR_RECETTES_API_GENERIC;
        }
    }

    public function fetchedRecettesAmountForCurrentShift(): ?float
    {
        if ($this->fetchedRecettesPanel === null) {
            return null;
        }

        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $k = $shift === 'lunch' ? 'lunch' : 'dinner';

        return (float) ($this->fetchedRecettesPanel[$k] ?? 0);
    }

    public function responsibleStaffDisplayName(): string
    {
        return Staff::query()->find($this->responsibleStaffId)?->name ?? '—';
    }

    public function currentShiftLabel(): string
    {
        return $this->shiftLabel($this->data['shift'] ?? null);
    }

    /**
     * Référence (pourboire + ventes POS) pour le shift courant.
     */
    public function runningTotalRefAmount(): float
    {
        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $prefix = $shift === 'lunch' ? 'lunch_' : 'dinner_';
        $ventes = round((float) ($this->data[$prefix.'recettes'] ?? 0), 3);
        $tip = round((float) ($this->data[$prefix.'chips'] ?? 0), 3);

        return round($ventes + $tip, 3);
    }

    /**
     * Total mesuré caisse (espèces + chèque + carte).
     */
    public function runningTotalMeasAmount(): float
    {
        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $prefix = $shift === 'lunch' ? 'lunch_' : 'dinner_';

        return round(
            (float) ($this->data[$prefix.'cash'] ?? 0)
                + (float) ($this->data[$prefix.'cheque'] ?? 0)
                + (float) ($this->data[$prefix.'carte'] ?? 0),
            3
        );
    }

    /**
     * 0–100 : 100 = écart nul (vert), diminue avec l’écart.
     */
    public function healthGaugePercent(): int
    {
        $ref = $this->runningTotalRefAmount();
        $meas = $this->runningTotalMeasAmount();
        $diff = abs(round($ref - $meas, 3));
        if ($diff < 0.0005) {
            return 100;
        }

        $tol = max($this->tolerancePlusValue, $this->toleranceMoinsValue);
        $scale = max($tol * 8, 5.0);

        return (int) max(5, min(99, round(100 - ($diff / $scale) * 100)));
    }

    /**
     * @return 'green'|'yellow'|'red'
     */
    public function healthGaugeTone(): string
    {
        $ref = $this->runningTotalRefAmount();
        $meas = $this->runningTotalMeasAmount();
        $diff = abs(round($ref - $meas, 3));
        if ($diff < 0.0005) {
            return 'green';
        }

        $tol = max($this->tolerancePlusValue, $this->toleranceMoinsValue);
        if ($diff <= $tol) {
            return 'yellow';
        }

        return 'red';
    }

    public function calculate(): void
    {
        $this->bannerError = '';
        $this->bannerSuccess = '';

        if (! $this->closeSessionReady || $this->responsibleStaffId === null) {
            $this->bannerError = 'Validez d’abord le service, le responsable et le code PIN.';

            return;
        }

        $rules = $this->rulesForSubmit();
        $this->validate($rules, [], $this->validationAttributes());

        $businessDate = (string) ($this->data['business_date'] ?? $this->businessDateStr);
        $this->businessDateStr = $businessDate;

        $payload = $this->payloadForSelectedShift($this->data);

        /** @var PersistDailyCloseResultAction $action */
        $action = app(PersistDailyCloseResultAction::class);
        $out = $action->execute(
            $businessDate,
            $this->data,
            $payload,
            $this->responsibleStaffId,
            $this->toleranceMoinsValue,
            $this->tolerancePlusValue,
            null,
        );

        if (($out['ok'] ?? false) === true) {
            /** @var array<string, mixed> $calc */
            $calc = $out['calc'];
            $this->resultModalCalc = $calc;
            $this->resultModalPayload = $payload;
            $this->resultModalShiftLabel = $this->shiftLabel($this->data['shift'] ?? null);
            $this->resultModalHint = $this->buildResultModalHint($calc);
            $this->resultModalKind = $calc['verdict'] === 'bravo' ? 'bravo' : 'retry';
            $this->resultModalDbSaved = true;
            $this->showResultOverlay = true;

            return;
        }

        if (($out['reason'] ?? '') === 'locked') {
            $this->bannerError = 'Traitement en cours. Attendez la fin de l’envoi, puis réessayez.';

            return;
        }

        $this->bannerError = 'Enregistrement en base avec statut « échec ». Vérifiez puis renvoyez.';
    }

    public function dismissResultOverlay(): void
    {
        $this->showResultOverlay = false;
    }

    public function toggleHistoryDetail(): void
    {
        $this->historyDetailOpen = ! $this->historyDetailOpen;
    }

    /**
     * @return Collection<int, Finance>
     */
    public function closeHistoryRows(): Collection
    {
        return Finance::query()
            ->with([
                'creator:id,name',
                'panelOperator:id,name',
                'responsibleStaff:id,name',
            ])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
    }

    public function formatMoneyCompact(float|string|null $n): string
    {
        $v = (float) $n;
        if (! is_finite($v)) {
            return '—';
        }
        $rounded = round($v, 2);
        if (abs($rounded - round($rounded)) < 0.0005) {
            return number_format((int) round($rounded), 0, '.', ',');
        }

        return number_format($rounded, 2, '.', ',');
    }

    /**
     * Dinar tunisien (TND) : 1 décimale ; affichage entier si la première décimale est 0.
     */
    public function formatTnd(float|string|null $n): string
    {
        $v = (float) $n;
        if (! is_finite($v)) {
            return '—';
        }
        $r = round($v, 1);
        $scaled = (int) round($r * 10);
        if ($scaled % 10 === 0) {
            return number_format((int) round($r), 0, '.', ',');
        }

        return number_format($r, 1, '.', ',');
    }

    /**
     * Valeur pour le champ texte Ventes POS (sans séparateur de milliers ; 1 décimale, entier si .0).
     */
    private function stringForVentesPosInput(float $amount): string
    {
        return CaisseMoneyInputNormalizer::normalizeToMaxOneDecimal($amount) ?? '0';
    }

    /**
     * Remplit Ventes POS (Midi ou Soir) avec le total API du service courant.
     */
    private function applyFetchedRecettesToVentesPosField(): void
    {
        if ($this->fetchedRecettesPanel === null) {
            return;
        }

        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $k = $shift === 'lunch' ? 'lunch' : 'dinner';
        $amount = (float) ($this->fetchedRecettesPanel[$k] ?? 0);
        $value = $this->stringForVentesPosInput($amount);

        if ($shift === 'lunch') {
            $this->data['lunch_recettes'] = $value;
        } else {
            $this->data['dinner_recettes'] = $value;
        }
    }

    public function historyResponsibleDisplay(Finance $h): string
    {
        if ($h->responsible_staff_id) {
            return $h->responsibleStaff?->name ?? '—';
        }
        if ($h->responsible_pin_verified) {
            return $h->creator?->name ?? '—';
        }

        return '—';
    }

    public function historyOperatorDisplay(Finance $h): string
    {
        return $h->panelOperator?->name ?? $h->creator?->name ?? '—';
    }

    public function historyVerdictLabel(Finance $h): string
    {
        return match ($h->verdict) {
            'bravo' => 'Parfait !',
            'plus_error' => 'Erreur (+)',
            'minus_error' => 'Erreur (-)',
            'failed' => 'Échec',
            default => (string) $h->verdict,
        };
    }

    public function render(): View
    {
        return view('livewire.frontend-daily-close');
    }

    /**
     * @return array<string, list<string|ValidationRule>>
     */
    private function rulesForSubmit(): array
    {
        $shift = (string) ($this->data['shift'] ?? 'dinner');

        $base = [
            'data.business_date' => ['required', 'date'],
            'data.shift' => ['required', 'in:lunch,dinner'],
        ];

        if ($shift === 'lunch') {
            return array_merge($base, [
                'data.lunch_recettes' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
                'data.lunch_montant_initial' => ['nullable', 'numeric'],
                'data.lunch_chips' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
                'data.lunch_cash' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
                'data.lunch_cheque' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
                'data.lunch_carte' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
            ]);
        }

        return array_merge($base, [
            'data.dinner_recettes' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
            'data.dinner_montant_initial' => ['nullable', 'numeric'],
            'data.dinner_chips' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
            'data.dinner_cash' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
            'data.dinner_cheque' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
            'data.dinner_carte' => ['required', 'numeric', 'decimal:0,1', 'min:0'],
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function validationAttributes(): array
    {
        return [
            'data.business_date' => 'Date',
            'data.shift' => 'Service',
            'data.lunch_recettes' => 'Ventes POS (Midi)',
            'data.lunch_chips' => 'Pourboire déclaré (Midi)',
            'data.lunch_cash' => 'Espèces (Midi)',
            'data.lunch_cheque' => 'Chèque (Midi)',
            'data.lunch_carte' => 'Carte (Midi)',
            'data.dinner_recettes' => 'Ventes POS (Soir)',
            'data.dinner_chips' => 'Pourboire déclaré (Soir)',
            'data.dinner_cash' => 'Espèces (Soir)',
            'data.dinner_cheque' => 'Chèque (Soir)',
            'data.dinner_carte' => 'Carte (Soir)',
        ];
    }

    private function hydrateCaisseConfig(): void
    {
        $fond = (float) Setting::getValue('fond_de_caisse', 100.000);
        $minus = (float) Setting::getValue('tolerance_moins', 1.000);
        $plus = (float) Setting::getValue('tolerance_plus', 3.000);

        $this->fondDeCaisseValue = round(max(0.0, $fond), 3);
        $this->toleranceMoinsValue = round(max(0.0, $minus), 3);
        $this->tolerancePlusValue = round(max(0.0, $plus), 3);
    }

    private function syncPriorBusinessDateForVentesSoir(): void
    {
        $d = $this->data['business_date'] ?? $this->businessDateStr ?? null;
        $this->priorBusinessDateForVentesSoir = is_string($d) && $d !== '' ? $d : null;
    }

    private function recettesFetchBlockedBySchedule(string $businessDateYmd, string $shift): ?string
    {
        $tz = config('app.timezone', 'UTC');
        $day = Carbon::parse($businessDateYmd, $tz)->startOfDay();
        if (! $day->isToday()) {
            return null;
        }

        $now = Carbon::now($tz);

        if ($shift === 'lunch') {
            $cutoff = Carbon::today($tz)->setTime(15, 0, 0);
            if ($now->lt($cutoff)) {
                return self::ERR_RECETTES_LUNCH_BEFORE_15H;
            }
        }

        if ($shift === 'dinner') {
            $cutoff = Carbon::today($tz)->setTime(22, 10, 0);
            if ($now->lt($cutoff)) {
                return self::ERR_RECETTES_DINNER_BEFORE_2210;
            }
        }

        return null;
    }

    private function shiftLabel(?string $shift): string
    {
        if ($shift === null || $shift === '') {
            return '';
        }

        return match ($shift) {
            'lunch' => 'Midi',
            'dinner' => 'Soir',
            default => $shift,
        };
    }

    /**
     * @param  array{tolerance: float|int, verdict: string, ...}  $calc
     */
    private function buildResultModalHint(array $calc): string
    {
        return match ($calc['verdict']) {
            'bravo' => 'Parfait ! La caisse correspond au pourboire déclaré + ventes POS.',
            'plus_error' => 'Attention : total caisse trop haut. Vérifiez espèces, chèques, carte et la saisie POS / pourboire.',
            'minus_error' => 'Attention : total caisse trop bas. Vérifiez tickets carte / chèque et la saisie POS / pourboire.',
            default => 'Vérifiez et renvoyez.',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, float|int|string|null>
     */
    private function payloadForSelectedShift(array $data): array
    {
        $shift = (string) ($data['shift'] ?? 'dinner');
        $prefix = $shift === 'lunch' ? 'lunch_' : 'dinner_';

        return [
            'recettes' => $data[$prefix.'recettes'] ?? 0,
            'cash' => $data[$prefix.'cash'] ?? 0,
            'cheque' => $data[$prefix.'cheque'] ?? 0,
            'carte' => $data[$prefix.'carte'] ?? 0,
            'chips' => $data[$prefix.'chips'] ?? 0,
            'montant_initial' => $data[$prefix.'montant_initial'] ?? 0,
        ];
    }
}
