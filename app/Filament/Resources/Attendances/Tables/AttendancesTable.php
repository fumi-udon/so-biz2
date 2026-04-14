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
use Filament\Tables\Columns\Layout\Split;
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
    /** Compact card border — no heavy shadow to save vertical weight */
    private const string RECORD_BLOCK =
        'att-dense-row mb-px rounded-lg border border-b-2 border-sky-200 bg-white dark:border-sky-800 dark:bg-slate-900';

    // ─── Tip state ───────────────────────────────────────────────────────────

    /**
     * @return 'eligible'|'denied'|'pending'|'no_punch'|'neutral'
     */
    private static function tipMealState(Attendance $record, string $meal): string
    {
        $applied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_applied ?? false)
            : (bool) ($record->is_dinner_tip_applied ?? false);
        $denied = $meal === 'lunch'
            ? (bool) ($record->is_lunch_tip_denied ?? false)
            : (bool) ($record->is_dinner_tip_denied ?? false);
        $clock = $meal === 'lunch' ? $record->lunch_in_at !== null : $record->dinner_in_at !== null;

        if ($denied) {
            return 'denied';
        }
        if ($applied) {
            $eligible = $meal === 'lunch'
                ? TipAttendanceScope::lunchEligible($record)
                : TipAttendanceScope::dinnerEligible($record);

            return $eligible ? 'eligible' : 'neutral';
        }
        if ($clock) {
            return 'pending';
        }

        return 'no_punch';
    }

    // ─── Format helpers ───────────────────────────────────────────────────────

    public static function formatMealRange(Attendance $record, string $meal): string
    {
        $in = $meal === 'lunch' ? $record->lunch_in_at : $record->dinner_in_at;
        $out = $meal === 'lunch' ? $record->lunch_out_at : $record->dinner_out_at;

        if (! $in && ! $out) {
            return '';
        }

        $inStr = $in instanceof Carbon ? $in->format('H:i') : '—';
        $outStr = $out instanceof Carbon ? $out->format('H:i') : '—';

        return $inStr.' – '.$outStr;
    }

    // ─── HTML builders ────────────────────────────────────────────────────────

    /**
     * One line: 14/04/2026 | Staff name
     */
    private static function identityLineHtml(Attendance $record): HtmlString
    {
        $dateStr = $record->date instanceof Carbon
            ? $record->date->format('d/m/Y')
            : Carbon::parse($record->date)->format('d/m/Y');
        $name = e((string) ($record->staff?->name ?? '—'));

        return new HtmlString(
            '<span class="inline-flex min-w-0 max-w-full items-center gap-1.5 text-[12px] leading-none">'
            .'<span class="shrink-0 font-mono tabular-nums font-semibold text-slate-700 dark:text-slate-200">'.e($dateStr).'</span>'
            .'<span class="shrink-0 text-slate-400 dark:text-slate-500">|</span>'
            .'<span class="min-w-0 truncate font-bold text-slate-900 dark:text-white">'.$name.'</span>'
            .'</span>'
        );
    }

    /**
     * One compact line per meal:
     *   ☀ 13:43 – 15:00  ✓   (tip eligible in amber)
     *   🌙 ——                 (no punch)
     */
    private static function mealSegmentHtml(Attendance $record, string $meal): string
    {
        $isLunch = $meal === 'lunch';
        $icon = $isLunch
            ? '<span class="text-amber-500 dark:text-amber-400 text-[12px]" title="Service midi">☀</span>'
            : '<span class="text-indigo-500 dark:text-indigo-400 text-[12px]" title="Service soir">🌙</span>';

        $timeText = self::formatMealRange($record, $meal);
        $hasPunch = $timeText !== '';

        $isAuto = $isLunch
            ? (bool) ($record->is_lunch_auto_clocked_out ?? false)
            : (bool) ($record->is_dinner_auto_clocked_out ?? false);
        $isEdited = $isAuto && (bool) ($record->is_edited_by_admin ?? false);

        if ($hasPunch) {
            $suffix = '';
            if ($isEdited) {
                $suffix = '<span class="text-violet-400 text-[8px]" title="Sortie auto-corrigée">✏</span>';
            } elseif ($isAuto) {
                $suffix = '<span class="text-sky-400 text-[8px]" title="Sortie automatique">🤖</span>';
            }
            $timeHtml = '<span class="font-mono tabular-nums text-[12px] text-slate-800 dark:text-gray-100">'.e($timeText).'</span>'.$suffix;
        } else {
            $timeHtml = '<span class="text-[12px] text-slate-500 dark:text-slate-400 tracking-widest">——</span>';
        }

        // Tip indicator: short text + color so mobile users understand without tooltip
        $tipState = self::tipMealState($record, $meal);
        $tipHtml = match ($tipState) {
            'eligible' => '<span class="rounded px-0.5 text-[9px] font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300/60 dark:border-amber-700/50" title="Tip éligible">Chp: 🪙</span>',
            'denied' => '<span class="rounded px-0.5 text-[9px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-900/40 dark:text-rose-300 border border-rose-300/60 dark:border-rose-700/50" title="Tip exclu">Chp: ❌</span>',
            'pending' => '<span class="rounded px-0.5 text-[9px] text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-slate-700" title="Tip non demandé">Chp: NO</span>',
            default => '',
        };

        return '<span class="inline-flex items-center gap-1">'
            .$icon
            .$timeHtml
            .($tipHtml !== '' ? $tipHtml : '')
            .'</span>';
    }

    /**
     * Both meals on one line: ☀ 13:43–15:00 🪙  ·  🌙 19:00–21:30 ✕
     */
    private static function mealsRowHtml(Attendance $record): HtmlString
    {
        $lunch = self::mealSegmentHtml($record, 'lunch');
        $dinner = self::mealSegmentHtml($record, 'dinner');

        return new HtmlString(
            '<span class="inline-flex flex-wrap items-center gap-x-2 gap-y-0 text-[12px] leading-none">'
            .$lunch
            .'<span class="text-slate-300 dark:text-slate-600 select-none">·</span>'
            .$dinner
            .'</span>'
        );
    }

    private static function lateRtdHtml(mixed $state): HtmlString
    {
        if ($state === null) {
            return new HtmlString(
                '<span class="font-mono text-[12px] tabular-nums text-slate-600 dark:text-slate-400">RTD: —</span>'
            );
        }

        $minutes = (int) $state;

        if ($minutes === 0) {
            return new HtmlString(
                '<span class="font-mono text-[12px] tabular-nums text-slate-600 dark:text-slate-400">RTD: 0</span>'
            );
        }

        return new HtmlString(
            '<span class="inline-flex animate-pulse rounded px-1 font-mono text-[12px] font-black tabular-nums text-rose-800 dark:text-rose-200">'
            .'RTD: '.e((string) $minutes)
            .'</span>'
        );
    }

    // ─── Table definition ─────────────────────────────────────────────────────

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->recordUrl(null)
            ->recordClasses(self::RECORD_BLOCK)
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
                        $data = AttendanceFormSaveData::finalizeForSave($data, null);
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
                // ── Responsive: stacks on mobile, side-by-side on md+ ──────────
                Split::make([

                    // ① Date | Staff — one line
                    TextColumn::make('identity_line')
                        ->label('')
                        ->html()
                        ->getStateUsing(fn (Attendance $record): HtmlString => self::identityLineHtml($record))
                        ->searchable(query: function (Builder $query, string $search): Builder {
                            return $query->whereHas('staff', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                        })
                        ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('date', $direction))
                        ->grow(false),

                    // ② Both meals on one line: ☀ hh:mm–hh:mm 🪙 · 🌙 ——
                    TextColumn::make('meals_row')
                        ->label('☀ Midi  ·  🌙 Soir')
                        ->html()
                        ->getStateUsing(fn (Attendance $record): HtmlString => self::mealsRowHtml($record)),

                    // ③ Retard — RTD: n
                    TextColumn::make('late_minutes')
                        ->label('')
                        ->formatStateUsing(fn ($state): HtmlString => self::lateRtdHtml($state))
                        ->html()
                        ->grow(false),

                ])->from('md'),
            ])
            ->actions([
                EditAction::make()
                    ->label('')
                    ->icon('heroicon-m-pencil-square')
                    ->iconButton()
                    ->tooltip(__('hq.action_edit', [], 'fr'))
                    ->size('sm')
                    ->color('warning')
                    ->slideOver()
                    ->form(fn (Form $form): Form => AttendanceForm::configure($form))
                    ->using(function (array $data, Model $record): Model {
                        /** @var Attendance $record */
                        $data = AttendanceFormSaveData::normalizeForRecord($record, $data);
                        AttendanceFormSaveData::assertAtLeastOneMealClockIn($data);
                        $data['is_edited_by_admin'] = true;
                        $record->update($data);

                        return $record->refresh();
                    }),
                DeleteAction::make()
                    ->label('')
                    ->icon('heroicon-m-trash')
                    ->iconButton()
                    ->tooltip('Supprimer')
                    ->size('sm')
                    ->color('danger'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
