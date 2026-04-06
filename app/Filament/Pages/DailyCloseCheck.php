<?php

namespace App\Filament\Pages;

use App\Jobs\NotifyDailyCloseMismatchJob;
use App\Models\Finance;
use App\Models\Setting;
use App\Models\Staff;
use App\Models\User;
use App\Services\BistronipponOrdersRecettesService;
use App\Services\FinanceCalculatorService;
use App\Services\FinanceCloseSnapshotBuilder;
use App\Support\BusinessDate;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithFormActions;
use Filament\Pages\Page;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\VerticalAlignment;
use Filament\Support\Enums\MaxWidth;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * @property Form $form
 */
class DailyCloseCheck extends Page
{
    use InteractsWithFormActions;

    /** Niveau métier minimum (colonne `job_levels.level`) pour figurer comme responsable de clôture. */
    private const MIN_CLOSE_GATE_JOB_LEVEL = 4;

    private const ERR_RECETTES_LUNCH_BEFORE_15H = 'Les données seront synchronisées après 15h00. Réessayez plus tard.';

    private const ERR_RECETTES_DINNER_BEFORE_2210 = 'Les données seront synchronisées après 22h00. Réessayez plus tard.';

    private const ERR_RECETTES_API_GENERIC = 'Impossible de récupérer les ventes. Vérifiez la connexion ou réessayez plus tard.';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user?->isPiloteOnly()) {
            return true;
        }

        return $user?->isAdmin() === true || $user?->isCashier() === true;
    }

    public static string|Alignment $formActionsAlignment = Alignment::Center;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static ?string $navigationLabel = 'Cloture caisse';

    protected static ?string $title = 'Cloture caisse';

    protected static ?string $navigationGroup = 'Caisse';

    protected static ?int $navigationSort = 12;

    protected static ?string $slug = 'daily-close-check';

    protected static string $view = 'filament.pages.daily-close-check';

    /**
     * @var array<mixed>
     */
    protected array $extraBodyAttributes = [
        'class' => 'daily-close-check-page',
    ];

    public string $businessDateStr = '';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /** 結果モーダル: bravo | retry */
    public string $resultModalKind = 'bravo';

    /**
     * @var array<string, mixed>
     */
    public array $resultModalCalc = [];

    /**
     * モーダル表示用（送信したシフトの入力スナップショット）
     *
     * @var array<string, mixed>
     */
    public array $resultModalPayload = [];

    public string $resultModalShiftLabel = '';

    public string $resultModalHint = '';

    public bool $resultModalDbSaved = false;

    /** 管理者 Door 用 PIN 入力（送信後は必ずクリア） */
    public string $doorPinInput = '';

    /** 画面下部「締め履歴」高密度テーブルの表示 */
    public bool $historyDetailOpen = false;

    /** 履歴行の「詳細」モーダル用 */
    public ?int $selectedHistoryFinanceId = null;

    /** 初期モーダル完了後のみレジ入力フォームを有効化 */
    public bool $closeSessionReady = false;

    /** 締め責任者（PIN 確認済み・staff.responsible_staff_id に保存） */
    public ?int $responsibleStaffId = null;

    /** 初期モーダル：締めるシフト（未選択時は null・既定の押下状態なし） */
    public ?string $gateShift = null;

    /** 初期モーダル：担当者 */
    public ?int $gateStaffId = null;

    /** 初期モーダル：本人確認（選択スタッフの pin_code・4桁） */
    public string $gatePinInput = '';

    private float $fondDeCaisseValue = 100.000;

    private float $toleranceMoinsValue = 1.000;

    private float $tolerancePlusValue = 3.000;

    /** Modal erreur API recettes (corps, français) */
    public ?string $recettesApiErrorModalBody = null;

    /**
     * Dernier résultat API (affichage en-tête uniquement — ne remplit pas Ventes POS).
     *
     * @var array{date: string, lunch: float, dinner: float, journal: float}|null
     */
    public ?array $fetchedRecettesPanel = null;

    /** Dernière `business_date` connue : changement réel → reset Ventes POS (soir). */
    private ?string $priorBusinessDateForVentesSoir = null;

    public function responsibleStaffDisplayName(): string
    {
        return Staff::query()->find($this->responsibleStaffId)?->name ?? '—';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    public function getHeader(): ?View
    {
        return view('filament.pages.daily-close-check-header');
    }

    public function getMaxContentWidth(): MaxWidth|string|null
    {
        return MaxWidth::SevenExtraLarge;
    }

    public function mount(): void
    {
        $this->hydrateCaisseConfig();

        $this->businessDateStr = BusinessDate::current()->toDateString();
        $this->closeSessionReady = false;
        $this->responsibleStaffId = null;
        $this->gateShift = null;
        $this->gateStaffId = null;
        $this->gatePinInput = '';

        $this->form->fill([
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
        ]);

        $this->syncPriorBusinessDateForVentesSoir();
    }

    /**
     * DatePicker（data.business_date）は native + wire:model のため、Filament の afterStateUpdated より
     * Livewire の updatedDataBusinessDate の方が確実に動く。
     *
     * 営業日が変わったら Ventes POS (midi) を数値ゼロではなく未入力（null＝空白）に戻す。
     */
    public function updatedDataBusinessDate(mixed $value): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $this->businessDateStr = $value;
        $this->resetRecettesApiUiState();

        if ($this->priorBusinessDateForVentesSoir !== null && $value !== $this->priorBusinessDateForVentesSoir) {
            // getState() は非表示シフトのフィールドを落とすことがあるため、既存 data をマージする
            $this->form->fill(array_merge($this->data ?? [], [
                'lunch_recettes' => null,
            ]));
            $this->fetchedRecettesPanel = null;
        }

        $this->priorBusinessDateForVentesSoir = $value;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema($this->getFormSchema())
            ->statePath('data');
    }

    /**
     * @return array<\Filament\Forms\Components\Component>
     */
    protected function getFormSchema(): array
    {
        $measured = 'Valeur mesuree dans la caisse.';

        $sectionShell = 'rounded-xl border-2 border-black bg-white shadow-[4px_4px_0_0_rgba(0,0,0,1)]';
        $fieldsetShell = 'rounded-lg border-2 border-black/10 bg-amber-50 p-2.5';

        $numeric = fn (string $name, string $label, ?string $helperText = null) => TextInput::make($name)
            ->label($label)
            ->numeric()
            ->step(0.001)
            ->minValue(0)
            ->maxValue(99999999.99)
            ->default(0)
            ->live(debounce: 500)
            ->extraInputAttributes(['class' => 'font-mono text-base tabular-nums'])
            ->when($helperText !== null, fn (TextInput $c) => $c->helperText($helperText));

        return [
            Section::make()
                ->extraAttributes(['class' => $sectionShell])
                ->schema([
                    Grid::make(['default' => 1, 'md' => 12])
                        ->schema([
                            DatePicker::make('business_date')
                                ->label('Date')
                                ->native(true)
                                ->required()
                                ->default($this->businessDateStr)
                                ->minDate(now()->subDays(3)->toDateString())
                                ->maxDate(now()->toDateString())
                                ->live(debounce: 0)
                                ->columnSpan(['default' => 1, 'md' => 5, 'lg' => 4]),
                            Actions::make([
                                FormAction::make('fetch_recettes_header')
                                    ->label('Get recettes')
                                    ->icon('heroicon-m-arrow-down-tray')
                                    ->color('warning')
                                    ->button()
                                    ->tooltip('Récupérer les ventes API (affichage en-tête, sans remplir Ventes POS)')
                                    ->action(fn () => $this->fetchRecettesFromApi()),
                            ])
                                ->key('daily-close-fetch-recettes-actions')
                                ->verticalAlignment(VerticalAlignment::End)
                                ->alignment(Alignment::Start)
                                ->columnSpan(['default' => 1, 'md' => 7, 'lg' => 8]),
                        ])
                        ->columns(12),
                    Hidden::make('shift')
                        ->default('dinner')
                        ->required(),
                ])
                ->columns(1),
            Section::make('Midi — Saisie caisse')
                ->description('Session midi uniquement.')
                ->extraAttributes(['class' => $sectionShell])
                ->visible(fn (Get $get): bool => $get('shift') === 'lunch')
                ->schema([
                    Fieldset::make('Parametres')
                        ->columns(1)
                        ->extraAttributes(['class' => $fieldsetShell])
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    $this->ventesPosTextInput('lunch', 'Ventes POS (midi)*', blankDefault: true),
                                    $numeric(
                                        'lunch_montant_initial',
                                        'Fond de caisse',
                                        'Valeur fixe du magasin (lecture seule).',
                                    )->disabled()->dehydrated(true),
                                    $numeric(
                                        'lunch_chips',
                                        'Tip déclaré (paramètres)*',
                                        'Saisi ici avec les ventes POS : tip déclaré + ventes POS doit égaler la mesure caisse (cash + chèque + carte).',
                                    )->required(fn (Get $get): bool => $get('shift') === 'lunch'),
                                ]),
                            Placeholder::make('lunch_params_running_total')
                                ->label('')
                                ->content(fn (Get $get): HtmlString => $this->runningTotalParamsHtml($get, 'lunch_'))
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Mesure caisse (cash, chèque, carte)')
                        ->columns(1)
                        ->extraAttributes(['class' => $fieldsetShell])
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2, 'lg' => 3])
                                ->schema([
                                    $numeric(
                                        'lunch_cash',
                                        'Cash (fond de caisse exclu)*',
                                        'Cash reel sans fond de caisse.',
                                    )->required(fn (Get $get): bool => $get('shift') === 'lunch'),
                                    $numeric(
                                        'lunch_cheque',
                                        'Cheque*',
                                        $measured,
                                    )->required(fn (Get $get): bool => $get('shift') === 'lunch'),
                                    $numeric(
                                        'lunch_carte',
                                        'Carte*',
                                        $measured.' Paiements carte.',
                                    )->required(fn (Get $get): bool => $get('shift') === 'lunch'),
                                ]),
                            Placeholder::make('lunch_measured_running_total')
                                ->label('')
                                ->content(fn (Get $get): HtmlString => $this->runningTotalMeasuredHtml($get, 'lunch_'))
                                ->columnSpanFull(),
                        ]),
                ]),
            Section::make('Soir — Saisie caisse')
                ->description('Session soir uniquement.')
                ->extraAttributes(['class' => $sectionShell])
                ->visible(fn (Get $get): bool => $get('shift') === 'dinner')
                ->schema([
                    Fieldset::make('Parametres')
                        ->columns(1)
                        ->extraAttributes(['class' => $fieldsetShell])
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 3])
                                ->schema([
                                    $this->ventesPosTextInput('dinner', 'Ventes POS (soir)*', blankDefault: false),
                                    $numeric(
                                        'dinner_montant_initial',
                                        'Fond de caisse',
                                        'Valeur fixe du magasin (lecture seule).',
                                    )->disabled()->dehydrated(true),
                                    $numeric(
                                        'dinner_chips',
                                        'Tip déclaré (paramètres)*',
                                        'Saisi ici avec les ventes POS : tip déclaré + ventes POS doit égaler la mesure caisse (cash + chèque + carte).',
                                    )->required(fn (Get $get): bool => $get('shift') === 'dinner'),
                                ]),
                            Placeholder::make('dinner_params_running_total')
                                ->label('')
                                ->content(fn (Get $get): HtmlString => $this->runningTotalParamsHtml($get, 'dinner_'))
                                ->columnSpanFull(),
                        ]),
                    Fieldset::make('Mesure caisse (cash, chèque, carte)')
                        ->columns(1)
                        ->extraAttributes(['class' => $fieldsetShell])
                        ->schema([
                            Grid::make(['default' => 1, 'md' => 2, 'lg' => 3])
                                ->schema([
                                    $numeric(
                                        'dinner_cash',
                                        'Cash (fond de caisse exclu)*',
                                        'Cash reel sans fond de caisse.',
                                    )->required(fn (Get $get): bool => $get('shift') === 'dinner'),
                                    $numeric(
                                        'dinner_cheque',
                                        'Cheque*',
                                        $measured,
                                    )->required(fn (Get $get): bool => $get('shift') === 'dinner'),
                                    $numeric(
                                        'dinner_carte',
                                        'Carte*',
                                        $measured.' Paiements carte.',
                                    )->required(fn (Get $get): bool => $get('shift') === 'dinner'),
                                ]),
                            Placeholder::make('dinner_measured_running_total')
                                ->label('')
                                ->content(fn (Get $get): HtmlString => $this->runningTotalMeasuredHtml($get, 'dinner_'))
                                ->columnSpanFull(),
                        ]),
                ]),
        ];
    }

    private function ventesPosTextInput(string $prefix, string $label, bool $blankDefault = false): TextInput
    {
        $name = $prefix.'_recettes';
        $shiftMatch = $prefix === 'lunch' ? 'lunch' : 'dinner';

        $field = TextInput::make($name)
            ->label($label)
            ->numeric()
            ->step(0.001)
            ->minValue(0)
            ->maxValue(99999999.99)
            ->live(debounce: 500)
            ->required(fn (Get $get): bool => $get('shift') === $shiftMatch)
            ->helperText('Montant ventes POS.')
            ->extraInputAttributes(['class' => 'font-mono text-base tabular-nums'])
            ->extraFieldWrapperAttributes(['class' => 'fi-ventes-pos-field-wrap']);

        if ($blankDefault) {
            return $field
                ->nullable()
                ->default(null);
        }

        return $field->default(0);
    }

    private function runningTotalParamsHtml(Get $get, string $prefix): HtmlString
    {
        $ventes = round((float) ($get($prefix.'recettes') ?? 0), 3);
        $tip = round((float) ($get($prefix.'chips') ?? 0), 3);
        $ref = round($ventes + $tip, 3);

        return new HtmlString(
            '<div class="mt-2 border-t border-dashed border-gray-300 pt-2 dark:border-white/15">'
            .'<span class="text-sm font-medium text-gray-950 dark:text-white">Référence (tip déclaré + ventes POS)</span>'
            .'<span class="ms-1 text-xs font-normal text-gray-500 dark:text-gray-400">DT</span>'
            .'<div class="mt-1 font-mono text-lg font-bold tabular-nums text-primary-600 dark:text-primary-400">'
            .e(number_format($ref, 3, '.', ','))
            .'</div>'
            .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-400">Doit égaler le total mesure caisse (cash + chèque + carte).</p>'
            .'</div>'
        );
    }

    private function runningTotalMeasuredHtml(Get $get, string $prefix): HtmlString
    {
        $sum = round(
            (float) ($get($prefix.'cash') ?? 0)
                + (float) ($get($prefix.'cheque') ?? 0)
                + (float) ($get($prefix.'carte') ?? 0),
            3
        );

        return new HtmlString(
            '<div class="mt-2 border-t border-dashed border-gray-300 pt-2 dark:border-white/15">'
            .'<span class="text-sm font-medium text-gray-950 dark:text-white">Total mesure caisse (cash + chèque + carte)</span>'
            .'<span class="ms-1 text-xs font-normal text-gray-500 dark:text-gray-400">DT</span>'
            .'<div class="mt-1 font-mono text-lg font-bold tabular-nums text-primary-600 dark:text-primary-400">'
            .e(number_format($sum, 3, '.', ','))
            .'</div></div>'
        );
    }

    private function shiftLabel(?string $shift): string
    {
        return match ($shift) {
            'lunch' => 'Midi',
            'dinner' => 'Soir',
            default => (string) $shift,
        };
    }

    public function fetchRecettesFromApi(): void
    {
        $date = (string) ($this->data['business_date'] ?? $this->businessDateStr ?? '');
        if ($date === '') {
            $this->openRecettesApiErrorModal('Sélectionnez une date.');

            return;
        }

        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $scheduleError = $this->recettesFetchBlockedBySchedule($date, $shift);
        if ($scheduleError !== null) {
            $this->openRecettesApiErrorModal($scheduleError);

            return;
        }

        $key = 'daily-close-fetch-recettes:'.(Filament::auth()->id() ?? 0).':'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 20)) {
            $this->openRecettesApiErrorModal('Trop de requêtes. Réessayez dans une minute.');

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
        } catch (Throwable $e) {
            Log::warning('daily_close.fetch_recettes_failed', ['exception' => $e]);
            $this->openRecettesApiErrorModal(self::ERR_RECETTES_API_GENERIC);
        }
    }

    public function openRecettesApiErrorModal(string $message): void
    {
        $this->recettesApiErrorModalBody = $message;
        $this->dispatch('open-modal', id: 'daily-close-recettes-api-error');
    }

    public function closeRecettesApiErrorModal(): void
    {
        $this->recettesApiErrorModalBody = null;
        $this->dispatch('close-modal', id: 'daily-close-recettes-api-error');
    }

    private function resetRecettesApiUiState(): void
    {
        $this->recettesApiErrorModalBody = null;
    }

    /**
     * 該当シフト分のみ（API パネル表示用）。
     */
    public function fetchedRecettesAmountForCurrentShift(): ?float
    {
        if ($this->fetchedRecettesPanel === null) {
            return null;
        }

        $shift = (string) ($this->data['shift'] ?? 'dinner');
        $key = $shift === 'lunch' ? 'lunch' : 'dinner';

        return (float) ($this->fetchedRecettesPanel[$key] ?? 0);
    }

    private function syncPriorBusinessDateForVentesSoir(): void
    {
        $d = $this->data['business_date'] ?? $this->businessDateStr ?? null;
        $this->priorBusinessDateForVentesSoir = is_string($d) && $d !== '' ? $d : null;
    }

    /**
     * 当日の締め日付のみ、POS 連携時刻前は取得不可（シフト別）。
     */
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

    public function openAdminDoorModal(): void
    {
        $this->doorPinInput = '';
        $this->dispatch('open-modal', id: 'daily-close-admin-door');
    }

    public function attemptUnlockAdminDoor(): void
    {
        $key = 'daily-close-door-pin:'.Filament::auth()->id().':'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            Notification::make()
                ->danger()
                ->title('試行制限')
                ->body('しばらくしてから再度お試しください。')
                ->send();

            return;
        }

        RateLimiter::hit($key, 60 * 5);

        $secret = (string) config('daily_close.door_secret', '');
        if ($secret === '') {
            Notification::make()
                ->warning()
                ->title('PIN が未設定')
                ->body('環境変数 DAILY_CLOSE_DOOR_SECRET を設定してください。')
                ->send();

            return;
        }

        if (! hash_equals($secret, $this->doorPinInput)) {
            $this->doorPinInput = '';
            Notification::make()
                ->danger()
                ->title('PIN が一致しません')
                ->send();

            return;
        }

        RateLimiter::clear($key);
        session([
            'daily_close_door_unlocked' => true,
            'daily_close_door_unlocked_until' => now()->addMinutes(10)->getTimestamp(),
        ]);
        $this->doorPinInput = '';
        Notification::make()
            ->success()
            ->title('参照モードを有効にしました')
            ->send();
    }

    public function lockAdminDoor(): void
    {
        session()->forget(['daily_close_door_unlocked', 'daily_close_door_unlocked_until']);
        Notification::make()
            ->success()
            ->title('参照モードを終了しました')
            ->send();
    }

    public function isAdminDoorUnlocked(): bool
    {
        if (! session('daily_close_door_unlocked', false)) {
            return false;
        }
        $until = session('daily_close_door_unlocked_until');
        if (! is_int($until) || $until < now()->getTimestamp()) {
            session()->forget(['daily_close_door_unlocked', 'daily_close_door_unlocked_until']);

            return false;
        }

        return true;
    }

    /**
     * @return Collection<int, Finance>
     */
    public function adminDoorFinanceRows(): Collection
    {
        if (! $this->isAdminDoorUnlocked()) {
            return new Collection([]);
        }

        return Finance::query()
            ->orderByDesc('created_at')
            ->limit(40)
            ->get();
    }

    /**
     * 履歴テーブル用：小数部が 0（.00）なら整数表示、それ以外は小数第2位まで。
     */
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
     * 画面下部：計算・送信で保存されたレジ締め履歴（一致・不一致・直近 50 件）。
     *
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

    /**
     * 履歴「責任者」列: responsible_staff_id 優先。旧 PIN 済みのみ User のみの行は creator。
     */
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

    /**
     * 履歴「操作者」列: パネル操作者（旧行はバックフィルで created_by と同一のことが多い）。
     */
    public function historyOperatorDisplay(Finance $h): string
    {
        return $h->panelOperator?->name ?? $h->creator?->name ?? '—';
    }

    public function historyResponsibleTitle(Finance $h): ?string
    {
        if ($h->responsible_staff_id) {
            return 'PIN 確定済みの締め責任者（スタッフ）';
        }
        if ($h->responsible_pin_verified) {
            return 'PIN 確定済み（ユーザー ID・移行前データの可能性）';
        }

        return '旧データ: 責任者は未記録（当時はパネル操作者のみ created_by）';
    }

    /**
     * 履歴「判定」列：アイコン名と Tailwind 色クラス。
     *
     * @return array{0: string, 1: string}
     */
    public function historyVerdictIcon(Finance $h): array
    {
        return match ($h->verdict) {
            'bravo' => ['heroicon-o-check-circle', 'h-4 w-4 shrink-0 text-success-600 dark:text-success-400'],
            'plus_error' => ['heroicon-o-exclamation-triangle', 'h-4 w-4 shrink-0 text-warning-600 dark:text-warning-400'],
            'minus_error' => ['heroicon-o-x-circle', 'h-4 w-4 shrink-0 text-danger-600 dark:text-danger-400'],
            'failed' => ['heroicon-o-x-octagon', 'h-4 w-4 shrink-0 text-danger-700 dark:text-danger-400'],
            default => ['heroicon-o-question-mark-circle', 'h-4 w-4 shrink-0 text-gray-500 dark:text-gray-400'],
        };
    }

    public function historyVerdictLabel(Finance $h): string
    {
        return match ($h->verdict) {
            'bravo' => 'Bravo',
            'plus_error' => 'Erreur (+)',
            'minus_error' => 'Erreur (-)',
            'failed' => 'Failed',
            default => (string) $h->verdict,
        };
    }

    public function toggleHistoryDetail(): void
    {
        $this->historyDetailOpen = ! $this->historyDetailOpen;
    }

    public function openHistorySnapshotModal(int $id): void
    {
        $this->selectedHistoryFinanceId = $id;
        $this->dispatch('open-modal', id: 'finance-history-snapshot-modal');
    }

    public function closeHistorySnapshotModal(): void
    {
        $this->selectedHistoryFinanceId = null;
    }

    /**
     * @return array<int, string>
     */
    public function staffOptions(): array
    {
        // jobLevel > 3 のみ取得に修正
        return Staff::query()
            ->where('is_active', true)
            ->whereNotNull('pin_code')
            ->where('pin_code', '!=', '')
            ->whereHas(
                'jobLevel',
                fn ($query) => $query->where('level', '>', 2),
            )
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function openSessionGateOnBoot(): void
    {
        if (! $this->closeSessionReady) {
            $this->dispatch('open-modal', id: 'daily-close-session-gate');
        }
    }

    public function openSessionGateModal(): void
    {
        $this->dispatch('open-modal', id: 'daily-close-session-gate');
    }

    public function closeGateAndGoTop(): void
    {
        $this->dispatch('close-modal', id: 'daily-close-session-gate');
        $this->redirect('/', navigate: true);
    }

    public function updatedGateStaffId(mixed $value): void
    {
        $this->gateStaffId = $value === '' || $value === null ? null : (int) $value;
    }

    public function updatedGateShift(mixed $value): void
    {
        if ($value === '' || $value === null) {
            $this->gateShift = null;
        }
    }

    public function confirmCloseSessionGate(): void
    {
        $this->resetErrorBag();

        $this->validate([
            'gateShift' => 'required|in:lunch,dinner',
            'gateStaffId' => 'required|integer|exists:staff,id',
            'gatePinInput' => 'required|string|digits:4',
        ], [
            'gateShift.required' => 'Choisis Midi ou Soir.',
            'gateStaffId.required' => 'Choisis un responsable.',
            'gatePinInput.required' => 'Saisis un PIN a 4 chiffres.',
            'gatePinInput.digits' => 'PIN: 4 chiffres.',
        ], [
            'gateShift' => 'Shift',
            'gateStaffId' => 'Responsable',
            'gatePinInput' => 'PIN',
        ]);

        $staffId = (int) $this->gateStaffId;
        $key = 'daily-close-gate-pin:staff:'.$staffId.':'.(request()->ip() ?? 'unknown');
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $this->addError('gatePinInput', 'Trop de tentatives PIN. Réessaie plus tard.');

            return;
        }

        $staff = Staff::query()
            ->where('is_active', true)
            ->whereHas(
                'jobLevel',
                fn ($query) => $query->where('level', '>=', self::MIN_CLOSE_GATE_JOB_LEVEL),
            )
            ->find($staffId);
        if ($staff === null) {
            $this->addError('gateStaffId', 'Responsable invalide ou niveau insuffisant (niveau 4+ requis).');

            return;
        }

        if ($staff->pin_code === null || $staff->pin_code === '') {
            $this->addError('gateStaffId', 'PIN non configure pour ce staff.');

            return;
        }

        if (! hash_equals((string) $staff->pin_code, (string) $this->gatePinInput)) {
            RateLimiter::hit($key, 300);
            $this->gatePinInput = '';
            $this->addError('gatePinInput', 'PIN incorrect.');

            return;
        }

        RateLimiter::clear($key);

        $this->responsibleStaffId = $staff->id;
        $this->gatePinInput = '';
        $this->gateStaffId = null;

        $this->form->fill(array_merge(
            is_array($this->data) ? $this->data : [],
            ['shift' => $this->gateShift]
        ));

        $this->syncPriorBusinessDateForVentesSoir();

        $this->closeSessionReady = true;
        $this->fetchedRecettesPanel = null;
        $this->dispatch('close-modal', id: 'daily-close-session-gate');

        Notification::make()
            ->success()
            ->title('Lock valide')
            ->body('Responsable confirme. Continue la saisie.')
            ->send();
    }

    public function reopenSessionGate(): void
    {
        $this->closeSessionReady = false;
        $this->responsibleStaffId = null;
        $this->gatePinInput = '';
        $this->gateStaffId = null;
        $this->gateShift = null;
        $this->fetchedRecettesPanel = null;
        $this->dispatch('open-modal', id: 'daily-close-session-gate');
    }

    public function selectedHistoryFinance(): ?Finance
    {
        if ($this->selectedHistoryFinanceId === null) {
            return null;
        }

        return Finance::query()
            ->with(['creator:id,name'])
            ->find($this->selectedHistoryFinanceId);
    }

    /**
     * @param  array{tolerance: float|int, verdict: string, ...}  $calc
     */
    private function buildResultModalHint(array $calc): string
    {
        return match ($calc['verdict']) {
            'bravo' => 'Bravo ! Mesure caisse = tip déclaré + ventes POS.',
            'plus_error' => 'Mesure caisse trop haute: revérifie comptage cash/chèque/carte ou tip/ventes POS.',
            'minus_error' => 'Mesure caisse trop basse: revérifie tickets carte/chèque ou tip/ventes POS.',
            default => 'Vérifie et renvoie.',
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

    public function calculate(): void
    {
        if (! $this->closeSessionReady || $this->responsibleStaffId === null) {
            Notification::make()
                ->danger()
                ->title('Lock requis')
                ->body('Valide d abord shift, responsable et PIN.')
                ->send();

            return;
        }

        $data = $this->form->getState();
        $businessDate = (string) ($data['business_date'] ?? $this->businessDateStr);
        $this->businessDateStr = $businessDate;

        $rateKey = 'filament:daily-close-check:'.Filament::auth()->id();
        $payload = $this->payloadForSelectedShift($data);

        $locked = Cache::lock($rateKey, 10)->get(function () use ($businessDate, $data, $payload): void {
            try {
                /** @var FinanceCalculatorService $service */
                $service = app(FinanceCalculatorService::class);

                $calc = $service->calculateResult(
                    $payload,
                    $this->toleranceMoinsValue,
                    $this->tolerancePlusValue,
                );

                $this->resultModalCalc = $calc;
                $this->resultModalPayload = $payload;
                $this->resultModalShiftLabel = $this->shiftLabel($data['shift'] ?? null);
                $this->resultModalHint = $this->buildResultModalHint($calc);
                $this->resultModalKind = $calc['verdict'] === 'bravo' ? 'bravo' : 'retry';
                $this->resultModalDbSaved = false;

                DB::transaction(function () use ($businessDate, $data, $calc, $payload): void {
                    Finance::query()->create([
                        'business_date' => $businessDate,
                        'shift' => $data['shift'],
                        'recettes' => $payload['recettes'],
                        'cash' => $payload['cash'],
                        'cheque' => $payload['cheque'],
                        'carte' => $payload['carte'],
                        'chips' => $payload['chips'],
                        'montant_initial' => $payload['montant_initial'] ?? 0,
                        'register_total' => $calc['measured_without_declared_tip'],
                        // 旧列 system_calculated_tip は互換維持のため残し、新ロジックの system_tip を格納。
                        'system_calculated_tip' => $calc['system_tip'],
                        'system_tip_amount' => $calc['system_tip'],
                        'declared_tip_amount' => $calc['declared_tip'],
                        'final_tip_amount' => $calc['final_tip_amount'],
                        'reserve_amount' => $calc['reserve_amount'],
                        'final_difference' => $calc['final_difference'],
                        'tolerance_used' => max($this->toleranceMoinsValue, $this->tolerancePlusValue),
                        'verdict' => $calc['verdict'],
                        'close_status' => $calc['close_status'],
                        'failure_reason' => $calc['verdict'] === 'bravo' ? null : 'outside_allowed_range',
                        'close_snapshot' => FinanceCloseSnapshotBuilder::build(
                            $payload,
                            $calc,
                            $businessDate,
                            (string) ($data['shift'] ?? 'dinner'),
                            $this->responsibleStaffId,
                            Filament::auth()->id(),
                        ),
                        'responsible_pin_verified' => true,
                        'panel_operator_user_id' => Filament::auth()->id(),
                        'responsible_staff_id' => $this->responsibleStaffId,
                        'created_by' => null,
                    ]);
                });
                $this->resultModalDbSaved = true;

                if ($calc['verdict'] !== 'bravo') {
                    NotifyDailyCloseMismatchJob::dispatch(
                        $this->responsibleStaffId,
                        $data,
                        $calc,
                    );
                }

                $this->dispatch('open-modal', id: 'daily-close-result-modal');
            } catch (Throwable $e) {
                // 送信押下時は異常系でも失敗レコードを残す。DB ダウン時は create が再例外となり通知が届かないため内側で隔離する。
                try {
                    Finance::query()->create([
                        'business_date' => $businessDate,
                        'shift' => (string) ($data['shift'] ?? 'dinner'),
                        'recettes' => $payload['recettes'] ?? 0,
                        'cash' => $payload['cash'] ?? 0,
                        'cheque' => $payload['cheque'] ?? 0,
                        'carte' => $payload['carte'] ?? 0,
                        'chips' => $payload['chips'] ?? 0,
                        'montant_initial' => $payload['montant_initial'] ?? 0,
                        'register_total' => 0,
                        'system_calculated_tip' => 0,
                        'system_tip_amount' => 0,
                        'declared_tip_amount' => $payload['chips'] ?? 0,
                        'final_tip_amount' => $payload['chips'] ?? 0,
                        'reserve_amount' => 0,
                        'final_difference' => 0,
                        'tolerance_used' => max($this->toleranceMoinsValue, $this->tolerancePlusValue),
                        'verdict' => 'failed',
                        'close_status' => 'failed',
                        'failure_reason' => mb_substr($e->getMessage(), 0, 240),
                        'responsible_pin_verified' => true,
                        'panel_operator_user_id' => Filament::auth()->id(),
                        'responsible_staff_id' => $this->responsibleStaffId,
                        'created_by' => null,
                    ]);
                } catch (Throwable $persistException) {
                    Log::critical('daily_close.calculate.failure_record_persist_failed', [
                        'original_exception' => $e->getMessage(),
                        'persist_exception' => $persistException->getMessage(),
                    ]);
                }

                Notification::make()
                    ->danger()
                    ->title('Erreur')
                    ->body('Enregistrement sauvegarde: état failed. Vérifie puis renvoie.')
                    ->send();
            }
        });

        if ($locked === false) {
            Notification::make()
                ->warning()
                ->title('Traitement en cours')
                ->body('Attends la fin de l envoi puis réessaie.')
                ->send();
        }
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('calculate')
                ->label('Calculer et envoyer')
                ->submit('calculate')
                ->color('warning')
                ->extraAttributes([
                    'wire:loading.attr' => 'disabled',
                    'wire:target' => 'calculate',
                ]),
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
}
