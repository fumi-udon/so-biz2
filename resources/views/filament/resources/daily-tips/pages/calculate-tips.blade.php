@php
    use Filament\Support\Enums\Alignment;

    $d = $this->data ?? [];
    $shift = $d['shift'] ?? 'lunch';
    $target = $this->normalizedTotalAmount();
    $match = abs($distributed_total - $target) < 0.0005;
    /** @var list<int> */
    $tipWeightOptions = range(0, 100, 10);
@endphp

<x-filament-panels::page
    class="-mt-2 max-w-full overflow-x-hidden [&_section.flex.flex-col]:gap-y-2 [&_section.flex.flex-col]:py-3"
>
    <div class="space-y-2 pb-28 sm:pb-24">
        {{-- フォーム: 営業日(Amber) / シフト(Indigo) / 総額(Sky) / 追加 — Section は CalculateTips.php で定義 --}}
        {{ $this->form }}

        {{-- 合計: Amber --}}
        <x-filament::section
            :compact="true"
            :heading="null"
            class="border-2 border-amber-200 bg-amber-50/50 shadow-sm ring-1 ring-amber-200/80 dark:border-amber-700 dark:bg-amber-950/30 dark:ring-amber-900/40"
        >
            <div
                class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs sm:text-sm"
            >
                <span class="font-semibold text-amber-900 dark:text-amber-200">配分合計 / 入力総額</span>
                <span
                    class="font-mono text-sm font-bold tabular-nums sm:text-base {{ $match ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-800 dark:text-amber-300' }}"
                >
                    {{ number_format($distributed_total, 3) }} / {{ number_format($target, 3) }} TND
                </span>
            </div>
        </x-filament::section>

        {{-- 配分テーブル: Emerald 枠 --}}
        <x-filament-tables::container
            class="border-2 border-emerald-600/30 bg-white shadow-md ring-2 ring-emerald-600/20 dark:border-emerald-600/40 dark:bg-gray-900 dark:ring-emerald-500/20"
        >
            <x-filament-tables::table class="table-fixed text-sm">
                <x-slot name="header">
                    <x-filament-tables::header-cell name="col_staff" :sortable="false">
                        スタッフ
                    </x-filament-tables::header-cell>
                    <x-filament-tables::header-cell
                        name="col_weight"
                        :sortable="false"
                        :alignment="Alignment::Center"
                        class="w-20 sm:w-24"
                    >
                        Weight
                    </x-filament-tables::header-cell>
                    <x-filament-tables::header-cell
                        name="col_amount"
                        :sortable="false"
                        :alignment="Alignment::End"
                    >
                        配分額
                    </x-filament-tables::header-cell>
                    <x-filament-tables::header-cell
                        name="col_actions"
                        :sortable="false"
                        :alignment="Alignment::End"
                        class="w-12"
                    >
                        <span class="sr-only">操作</span>
                    </x-filament-tables::header-cell>
                </x-slot>

                @forelse($rows as $index => $row)
                    <x-filament-tables::row :striped="$loop->even" class="align-middle">
                        <x-filament-tables::cell class="!px-2 !py-1.5 align-middle">
                            <div class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-0.5">
                                <span class="font-semibold text-gray-950 dark:text-white">
                                    {{ $row['name'] }}
                                </span>
                                <x-filament::badge color="gray" size="xs">
                                    {{ $shift === 'lunch' ? '昼' : '夜' }}
                                </x-filament::badge>
                                @if(! empty($row['is_tardy_deprived']))
                                    <x-filament::badge
                                        color="danger"
                                        size="xs"
                                        class="ring-1 ring-rose-600/50 !bg-rose-50 !text-rose-700 dark:!bg-rose-950/50 dark:!text-rose-300 dark:ring-rose-600/40"
                                    >
                                        遅
                                    </x-filament::badge>
                                @endif
                            </div>
                            <div>
                                <span class="truncate text-[11px] leading-tight text-gray-500 dark:text-gray-400">
                                    {{ $row['job_level'] }}
                                </span>
                            </div>
                        </x-filament-tables::cell>
                        <x-filament-tables::cell
                            class="!border-s !border-sky-200/80 !bg-sky-50/50 !px-1 !py-1 align-middle dark:!border-sky-800/60 dark:!bg-sky-950/30"
                        >
                            <x-filament::input.wrapper
                                class="mx-auto w-full max-w-[5.5rem] rounded-md border-2 border-sky-300 bg-white py-0 shadow-inner dark:border-sky-600 dark:bg-gray-900"
                            >
                                <x-filament::input.select
                                    wire:model.live.debounce.500ms="rows.{{ $index }}.weight"
                                    class="!py-1 !pe-7 !ps-2 !text-center !text-sm !font-medium !tabular-nums text-sky-900 dark:text-sky-100"
                                >
                                    @foreach($tipWeightOptions as $w)
                                        <option value="{{ $w }}">{{ $w }}</option>
                                    @endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                        </x-filament-tables::cell>
                        <x-filament-tables::cell
                            class="!px-2 !py-1.5 text-end align-middle"
                            tag="td"
                        >
                            <span
                                class="text-lg font-bold tabular-nums text-emerald-600 dark:text-emerald-400"
                            >
                                {{ number_format((float) $row['amount'], 3) }}
                            </span>
                            <span class="ms-0.5 text-[10px] font-medium text-emerald-700/70 dark:text-emerald-500/80"
                                >TND</span
                            >
                        </x-filament-tables::cell>
                        <x-filament-tables::cell class="!px-1 !py-1.5 text-end align-middle">
                            <x-filament::button
                                color="gray"
                                size="xs"
                                outlined
                                type="button"
                                wire:click="removeStaff({{ (int) $row['staff_id'] }})"
                                wire:loading.attr="disabled"
                            >
                                削除
                            </x-filament::button>
                        </x-filament-tables::cell>
                    </x-filament-tables::row>
                @empty
                    <x-filament-tables::row>
                        <x-filament-tables::cell
                            class="!border-t-2 !border-dashed !border-emerald-200 !px-3 !py-8 text-center text-xs font-medium text-gray-500 dark:!border-emerald-900 dark:text-gray-400"
                            colspan="4"
                            tag="td"
                        >
                            この条件で出勤のスタッフはいません。上の「スタッフを追加」から手動で加えてください。
                        </x-filament-tables::cell>
                    </x-filament-tables::row>
                @endforelse
            </x-filament-tables::table>
        </x-filament-tables::container>

        {{-- 週次・月次（Emerald 系） --}}
        <x-filament::section
            :compact="true"
            :heading="null"
            class="border-2 border-emerald-600/25 bg-emerald-50/40 shadow-sm ring-1 ring-emerald-600/20 dark:border-emerald-700/50 dark:bg-emerald-950/15 dark:ring-emerald-900/40"
        >
            <div
                class="flex flex-wrap items-baseline gap-x-3 gap-y-0 text-xs text-emerald-900 dark:text-emerald-200"
            >
                <span class="font-semibold">今週計</span>
                <span class="font-mono font-medium tabular-nums text-emerald-700 dark:text-emerald-300">
                    {{ number_format($weekly_total, 3) }} TND
                </span>
                <span class="text-emerald-300 dark:text-emerald-800">|</span>
                <span class="font-semibold">今月計</span>
                <span class="font-mono font-medium tabular-nums text-emerald-700 dark:text-emerald-300">
                    {{ number_format($monthly_total, 3) }} TND
                </span>
            </div>
        </x-filament::section>
    </div>

    <div
        class="sticky bottom-0 z-30 border-t-4 border-emerald-600 bg-gradient-to-t from-emerald-50/95 to-white/95 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-2 backdrop-blur-md dark:from-emerald-950/90 dark:to-gray-950/95"
    >
        <x-filament::button
            color="success"
            type="button"
            wire:click="confirm"
            wire:loading.attr="disabled"
            class="w-full min-h-[48px] justify-center rounded-xl border-2 border-emerald-700 bg-gradient-to-b from-emerald-500 to-emerald-600 px-3 py-3 text-base font-bold text-white shadow-lg shadow-emerald-600/30 hover:from-emerald-400 hover:to-emerald-500 focus:ring-4 focus:ring-emerald-400/50 dark:border-emerald-500 dark:from-emerald-600 dark:to-emerald-700 dark:hover:from-emerald-500 dark:hover:to-emerald-600"
        >
            確定して保存
        </x-filament::button>
    </div>

    <div
        wire:loading.delay
        wire:loading.class.remove="hidden"
        wire:loading.class="flex"
        class="hidden fixed inset-0 z-40 items-start justify-center bg-gray-950/35 pt-24 backdrop-blur-sm dark:bg-gray-950/55"
    >
        <x-filament::loading-indicator class="h-10 w-10 text-primary-600" />
    </div>
</x-filament-panels::page>
