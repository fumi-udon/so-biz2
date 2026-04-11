<?php

namespace App\Filament\Resources\Attendances\Tables;

use App\Filament\Resources\Attendances\AttendanceResource;
use App\Filament\Resources\Attendances\Forms\AttendanceForm;
use App\Models\Attendance;
use App\Models\Staff;
use App\Support\AttendanceFormSaveData;
use App\Support\BusinessDate;
use App\Support\TipAttendanceScope;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class AttendancesTable
{
    private const string HEADER_CELL =
        'text-xs font-black uppercase tracking-wide text-slate-900 dark:text-slate-100';

    private const string RECORD_BLOCK =
        'mb-3 rounded-xl border-2 border-b-4 border-sky-300 bg-gradient-to-br from-white via-sky-50/70 to-cyan-50/50 shadow-md ring-1 ring-sky-200/70 dark:border-sky-700 dark:from-slate-900 dark:via-slate-900 dark:to-sky-950/40 dark:ring-sky-800/50';

    /**
     * @return 'eligible'|'denied'|'pending'|'no_punch'|'neutral'
     */
    private static function tipMealState(Attendance $record, string $meal): string
    {
        $clock = $meal === 'lunch' ? $record->lunch_in_at !== null : $record->dinner_in_at !== null;
        $applied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_applied ?? false)
            : (bool) ($record->is_dinner_tip_applied ?? false);
        $denied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_denied ?? false)
            : (bool) ($record->is_dinner_tip_denied ?? false);

        if ($denied) {
            return 'denied';
        }
        if ($clock && $applied && ! $denied) {
            $eligible = $meal === 'lunch'
                ? TipAttendanceScope::lunchEligible($record)
                : TipAttendanceScope::dinnerEligible($record);

            return $eligible ? 'eligible' : 'neutral';
        }
        if ($clock && ! $applied) {
            return 'pending';
        }
        if (! $clock) {
            return 'no_punch';
        }

        return 'neutral';
    }

    public static function formatMealRange(Attendance $record, string $meal): string
    {
        $inKey = $meal === 'lunch' ? 'lunch_in_at' : 'dinner_in_at';
        $outKey = $meal === 'lunch' ? 'lunch_out_at' : 'dinner_out_at';
        $in = $record->{$inKey};
        $out = $record->{$outKey};

        if (! $in && ! $out) {
            return '—';
        }

        $inStr = $in instanceof Carbon ? $in->format('H:i') : '—';
        $outStr = $out instanceof Carbon ? $out->format('H:i') : '—';

        return $inStr.' – '.$outStr;
    }

    public static function mealRangeHtml(Attendance $record, string $meal): HtmlString
    {
        $text = self::formatMealRange($record, $meal);
        $hasPunch = $text !== '—';
        $boxClass = $hasPunch
            ? 'inline-flex max-w-full rounded-lg border border-emerald-300/80 bg-emerald-100 px-2 py-1 font-mono text-xs font-bold tabular-nums text-emerald-950 shadow-sm ring-1 ring-emerald-200/80 dark:border-emerald-700/50 dark:bg-emerald-950/50 dark:text-emerald-100 dark:ring-emerald-800/50'
            : 'inline-flex rounded-lg border border-slate-200 bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-600 ring-1 ring-slate-200/80 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700/80';

        return new HtmlString('<span class="'.$boxClass.'">'.e($text).'</span>');
    }

    public static function tipMealBadgeHtml(Attendance $record, string $meal): HtmlString
    {
        $state = self::tipMealState($record, $meal);
        $text = match ($state) {
            'eligible' => '🪙 '.__('hq.tip_eligible', [], 'fr'),
            'denied' => '❌ '.__('hq.tip_denied', [], 'fr'),
            'pending' => '⚪️ '.__('hq.tip_pending', [], 'fr'),
            'no_punch' => '⚪️ '.__('hq.tip_no_punch', [], 'fr'),
            'neutral' => '—',
            default => '—',
        };

        $wrapClass = match ($state) {
            'eligible' => 'inline-flex max-w-full items-center gap-0.5 rounded-full border border-amber-300/80 bg-amber-100 px-2 py-0.5 text-xs font-bold text-amber-950 shadow-sm ring-1 ring-amber-200/90 dark:border-amber-700/50 dark:bg-amber-950/45 dark:text-amber-100 dark:ring-amber-900/40',
            'denied' => 'inline-flex max-w-full items-center gap-0.5 rounded-full border border-rose-300/80 bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-900 shadow-sm ring-1 ring-rose-200/90 dark:border-rose-700/50 dark:bg-rose-950/50 dark:text-rose-100 dark:ring-rose-900/40',
            'pending' => 'inline-flex max-w-full items-center gap-0.5 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-bold text-slate-800 ring-1 ring-slate-200/90 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-700/80',
            'no_punch' => 'inline-flex max-w-full items-center gap-0.5 rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600 ring-1 ring-slate-200/80 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-400 dark:ring-slate-700/80',
            'neutral' => 'inline-flex max-w-full items-center rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 font-mono text-xs font-bold text-slate-500 ring-1 ring-slate-200/80 dark:border-slate-600 dark:bg-slate-800/80 dark:text-slate-400 dark:ring-slate-700/80',
            default => 'inline-flex text-slate-400 dark:text-slate-500',
        };

        return new HtmlString('<span class="'.$wrapClass.'">'.e($text).'</span>');
    }

    public static function lateMinutesHtml(mixed $state): HtmlString
    {
        if ($state === null) {
            return new HtmlString('<span class="text-slate-400 dark:text-slate-500">—</span>');
        }

        $minutes = (int) $state;
        if ($minutes === 0) {
            return new HtmlString(
                '<span class="inline-flex rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 font-mono text-xs font-bold tabular-nums text-slate-700 ring-1 ring-slate-200/80 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700/80">0</span>'
            );
        }

        return new HtmlString(
            '<span class="inline-flex animate-pulse rounded-full border border-rose-300 bg-rose-100 px-2 py-0.5 font-mono text-xs font-black tabular-nums text-rose-800 shadow-sm ring-1 ring-rose-300/90 dark:border-rose-600 dark:bg-rose-950/60 dark:text-rose-200 dark:ring-rose-800/60">'
            .e((string) $minutes)
            .'</span>'
        );
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->recordUrl(null)
            ->recordClasses(self::RECORD_BLOCK)
            ->actionsColumnLabel(__('hq.col_actions', [], 'fr'))
            ->filters([
                SelectFilter::make('staff_id')
                    ->label(__('hq.filter_staff', [], 'fr'))
                    ->options(fn (): array => Staff::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                Filter::make('month')
                    ->form([
                        DatePicker::make('month_filter')
                            ->label(__('hq.filter_month', [], 'fr'))
                            ->native(false)
                            ->displayFormat('m / Y')
                            ->default(BusinessDate::current()->startOfMonth()),
                    ])
                    ->query(function (Builder $query, array $data): void {
                        $raw = $data['month_filter'] ?? null;
                        $d = blank($raw)
                            ? BusinessDate::current()->startOfMonth()
                            : Carbon::parse($raw);
                        $query->whereYear('date', $d->year)->whereMonth('date', $d->month);
                    }),
            ], layout: FiltersLayout::Dropdown)
            ->filtersFormColumns(1)
            ->headerActions([
                CreateAction::make()
                    ->label(__('hq.action_new_punch', [], 'fr'))
                    ->slideOver()
                    ->form(fn (Form $form): Form => AttendanceForm::configure($form))
                    ->using(function (array $data, Table $table): Model {
                        $data = AttendanceFormSaveData::normalizeForCreate($data);
                        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);

                        $existing = Attendance::query()
                            ->where('staff_id', $data['staff_id'])
                            ->whereDate('date', $data['date'])
                            ->first();

                        if ($existing !== null) {
                            Notification::make()
                                ->warning()
                                ->title(__('hq.notify_duplicate_table', [], 'fr'))
                                ->send();

                            $livewire = $table->getLivewire();
                            $livewire->redirect(AttendanceResource::getUrl('edit', ['record' => $existing]));

                            throw new Halt;
                        }

                        return Attendance::query()->create($data);
                    }),
            ])
            ->columns([
                TextColumn::make('date')
                    ->label(__('hq.col_date', [], 'fr'))
                    ->date('d/m/Y')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('staff.name')
                    ->label(__('hq.col_staff', [], 'fr'))
                    ->searchable()
                    ->weight('bold')
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('lunch')
                    ->label(__('hq.col_lunch', [], 'fr'))
                    ->html()
                    ->getStateUsing(fn (Attendance $record): HtmlString => self::mealRangeHtml($record, 'lunch'))
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('dinner')
                    ->label(__('hq.col_dinner', [], 'fr'))
                    ->html()
                    ->getStateUsing(fn (Attendance $record): HtmlString => self::mealRangeHtml($record, 'dinner'))
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('tip_lunch')
                    ->label(__('hq.col_tip_l', [], 'fr'))
                    ->html()
                    ->getStateUsing(fn (Attendance $record): HtmlString => self::tipMealBadgeHtml($record, 'lunch'))
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('tip_dinner')
                    ->label(__('hq.col_tip_d', [], 'fr'))
                    ->html()
                    ->getStateUsing(fn (Attendance $record): HtmlString => self::tipMealBadgeHtml($record, 'dinner'))
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
                TextColumn::make('late_minutes')
                    ->label(__('hq.col_late_min', [], 'fr'))
                    ->formatStateUsing(fn ($state): HtmlString => self::lateMinutesHtml($state))
                    ->html()
                    ->extraHeaderAttributes(['class' => self::HEADER_CELL]),
            ])
            ->actions([
                EditAction::make()
                    ->label(__('hq.action_edit_mushroom', [], 'fr'))
                    ->icon(null)
                    ->extraAttributes([
                        'class' => 'inline-flex items-center justify-center rounded-xl border-2 border-b-4 border-red-700 bg-red-500 px-3 py-1.5 text-xs font-black uppercase tracking-wide text-white shadow-md transition hover:bg-red-600 active:border-b-2 active:translate-y-0.5 dark:border-red-800 dark:bg-red-700 dark:hover:bg-red-600',
                    ])
                    ->slideOver()
                    ->form(fn (Form $form): Form => AttendanceForm::configure($form))
                    ->using(function (array $data, Model $record, Table $table): Model {
                        /** @var Attendance $record */
                        $data = AttendanceFormSaveData::normalizeForRecord($record, $data);
                        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);
                        $data['is_edited_by_admin'] = true;
                        $record->update($data);

                        return $record->refresh();
                    }),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
