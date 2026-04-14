<?php

namespace App\Filament\Resources\DailyTips;

use App\Filament\Resources\DailyTips\Pages\CalculateTips;
use App\Filament\Resources\DailyTips\Pages\CreateDailyTip;
use App\Filament\Resources\DailyTips\Pages\EditDailyTip;
use App\Filament\Resources\DailyTips\Pages\ListDailyTips;
use App\Filament\Resources\DailyTips\Pages\TipDashboard;
use App\Filament\Resources\DailyTips\RelationManagers\DailyTipDistributionRelationManager;
use App\Filament\Support\AdminOnlyResource;
use App\Models\DailyTip;
use App\Models\Finance;
use Carbon\Carbon;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Pages\EditRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Livewire\Component as LivewireComponent;

class DailyTipResource extends AdminOnlyResource
{
    protected static function piloteCanAccessThisResource(): bool
    {
        return true;
    }

    protected static ?string $model = DailyTip::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Pourboires';

    protected static ?string $modelLabel = 'pourboire';

    protected static ?string $pluralModelLabel = 'pourboires';

    /**
     * Blocs « Mario » : amber / sky / emerald uniquement (pas d’orange).
     */
    private const MARIO_BLOCK = 'rounded-2xl border-2 border-b-[6px] shadow-sm ring-1 transition-colors';

    public static function form(Form $form): Form
    {
        return $form
            ->columns(1)
            ->schema([
                Hidden::make('is_finance_info_visible')
                    ->default(false)
                    ->dehydrated(false),
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
                                    ->helperText('Date de service concernée par la répartition.')
                                    ->extraInputAttributes([
                                        'class' => 'py-2 text-base text-gray-950 dark:text-white',
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => self::MARIO_BLOCK.' border-amber-400 bg-amber-50/90 p-4 ring-amber-200/80 dark:border-amber-600 dark:bg-amber-950/40 dark:ring-amber-900/50',
                            ]),
                        Section::make()
                            ->heading(null)
                            ->compact()
                            ->schema([
                                Select::make('shift')
                                    ->label('🍽️ Service')
                                    ->required()
                                    ->native(true)
                                    ->placeholder('Choisir')
                                    ->live()
                                    ->options([
                                        'lunch' => '☀️ Midi',
                                        'dinner' => '🌙 Soir',
                                    ])
                                    ->extraInputAttributes([
                                        'class' => 'py-2 text-sm text-gray-950 dark:text-white',
                                    ]),
                            ])
                            ->extraAttributes([
                                'class' => self::MARIO_BLOCK.' border-sky-400 bg-sky-50/90 p-4 ring-sky-200/80 dark:border-sky-600 dark:bg-sky-950/40 dark:ring-sky-900/50',
                            ]),
                    ]),
                Placeholder::make('overwrite_notice')
                    ->label('')
                    ->content(new HtmlString(
                        '<p class="text-sm font-semibold leading-snug text-rose-800 dark:text-rose-100">'
                        .'⚠️ Un pourboire existe déjà pour cette date et ce service. L’enregistrement <span class="underline">remplacera</span> les montants et recalculera les répartitions.'
                        .'</p>'
                    ))
                    ->visible(function (Get $get, LivewireComponent $livewire): bool {
                        if (! filled($get('business_date')) || ! filled($get('shift'))) {
                            return false;
                        }

                        $date = Carbon::parse($get('business_date'))->toDateString();
                        $shift = (string) $get('shift');

                        $query = DailyTip::query()
                            ->whereDate('business_date', $date)
                            ->where('shift', $shift);

                        if ($livewire instanceof EditRecord && $livewire->getRecord()?->exists) {
                            $query->whereKeyNot($livewire->getRecord()->getKey());
                        }

                        return $query->exists();
                    })
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::MARIO_BLOCK.' border-rose-400 bg-rose-50/95 p-3 ring-rose-200/80 dark:border-rose-600 dark:bg-rose-950/40 dark:ring-rose-900/50',
                    ]),
                Section::make()
                    ->heading(null)
                    ->compact()
                    ->schema([
                        TextInput::make('total_amount')
                            ->label('💰 Total pourboires')
                            ->required()
                            ->numeric()
                            ->step(0.001)
                            ->minValue(0)
                            ->suffix('DT')
                            ->helperText('Jusqu’à 3 décimales. Astuce : ouvrez l’historique caisse ci-dessous.')
                            ->hintAction(
                                FormAction::make('toggleFinancePanel')
                                    ->label('Caisse')
                                    ->icon('heroicon-o-chevron-down')
                                    ->extraAttributes([
                                        'class' => '!rounded-xl !border-amber-600 !bg-amber-400 border-b-[6px] !px-2 !py-1 !text-xs !font-black !text-white transition-all active:!translate-y-[6px] active:!border-b-0',
                                    ])
                                    ->action(function (Set $set, Get $get): void {
                                        $set('is_finance_info_visible', ! (bool) $get('is_finance_info_visible'));
                                    })
                            )
                            ->extraInputAttributes([
                                'class' => 'py-2.5 text-2xl font-black tabular-nums leading-tight text-gray-950 dark:text-white',
                                'step' => '0.001',
                                'inputmode' => 'decimal',
                            ]),
                        Section::make('Historique caisse (7 jours)')
                            ->description('Dernière clôture réussie par jour et par service (chips déclarés).')
                            ->schema([
                                Placeholder::make('finance_history_body')
                                    ->label('')
                                    ->content(function (Get $get): HtmlString {
                                        return self::buildFinanceHistoryContent($get('business_date'));
                                    }),
                            ])
                            ->collapsed(false)
                            ->visible(fn (Get $get): bool => (bool) $get('is_finance_info_visible'))
                            ->compact()
                            ->extraAttributes([
                                'class' => 'mt-2 overflow-hidden rounded-xl border-2 border-dashed border-sky-300/80 bg-white/80 p-3 text-gray-950 shadow-inner ring-1 ring-sky-100 dark:border-sky-700 dark:bg-sky-950/30 dark:text-white dark:ring-sky-900/40',
                            ]),
                    ])
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::MARIO_BLOCK.' border-emerald-500 bg-emerald-50/90 p-4 ring-emerald-200/80 dark:border-emerald-600 dark:bg-emerald-950/40 dark:ring-emerald-900/50',
                    ]),
            ]);
    }

    /**
     * @return HtmlString Lignes puce : date, service, chips (clôture réussie), fenêtre glissante de 7 jours jusqu’à la date du formulaire.
     */
    public static function buildFinanceHistoryContent(mixed $businessDateState): HtmlString
    {
        $anchor = $businessDateState
            ? Carbon::parse($businessDateState)
            : Carbon::today();

        $start = $anchor->copy()->subDays(6)->startOfDay();
        $lines = [];

        for ($d = $start->copy(); $d->lte($anchor); $d->addDay()) {
            $dateStr = $d->toDateString();
            foreach (['lunch', 'dinner'] as $shift) {
                $row = Finance::query()
                    ->whereDate('business_date', $dateStr)
                    ->where('shift', $shift)
                    ->where('close_status', 'success')
                    ->latest('id')
                    ->first();

                if ($row === null) {
                    continue;
                }

                $serviceLabel = $shift === 'lunch' ? 'Midi' : 'Soir';
                $chips = number_format((float) ($row->chips ?? 0), 3, ',', ' ');
                $lines[] = sprintf(
                    '• %s · %s · %s DT (chips)',
                    $d->format('d/m/Y'),
                    $serviceLabel,
                    $chips
                );
            }
        }

        if ($lines === []) {
            return new HtmlString(
                '<p class="text-sm text-gray-700 dark:text-gray-200">Aucune clôture caisse enregistrée sur les 7 derniers jours pour cette fenêtre.</p>'
            );
        }

        $html = '<ul class="list-none space-y-1.5 text-sm font-medium text-gray-900 dark:text-gray-100">';
        foreach ($lines as $line) {
            $html .= '<li class="border-b border-sky-200/60 pb-1 last:border-0 dark:border-sky-800/60">'.e($line).'</li>';
        }
        $html .= '</ul>';

        return new HtmlString($html);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('business_date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('shift')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'lunch' => 'Midi',
                        'dinner' => 'Soir',
                        default => $state ?? '—',
                    }),
                TextColumn::make('total_amount')
                    ->label('Total (DT)')
                    ->numeric(decimalPlaces: 3),
                TextColumn::make('distributions_count')
                    ->label('Personnes')
                    ->counts('distributions'),
            ])
            ->defaultSort('business_date', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            DailyTipDistributionRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => TipDashboard::route('/'),
            'list_all' => ListDailyTips::route('/list-all'),
            'calculate' => CalculateTips::route('/calculate'),
            'create' => CreateDailyTip::route('/create'),
            'edit' => EditDailyTip::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('distributions');
    }
}
