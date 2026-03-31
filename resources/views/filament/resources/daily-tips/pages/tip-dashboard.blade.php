<x-filament-panels::page>
    @php
        $w = $this->weekMatrix;
        $m = $this->monthAnalysis;
        $barColors = ['bg-rose-500', 'bg-amber-500', 'bg-emerald-500', 'bg-sky-500', 'bg-violet-500', 'bg-teal-500', 'bg-orange-500', 'bg-cyan-500'];
        /** 表示専用：第1位切り捨てのうえ、.0 は省略（集計・DBは変更しない） */
        $tipSmart = static function (float $v): string {
            $d = floor($v * 10) / 10;

            return rtrim(rtrim(number_format($d, 1, '.', ''), '0'), '.') ?: '0';
        };
    @endphp

    {{-- 週ナビ：1ブロックに圧縮 --}}
    <div class="mb-4 rounded-xl border-2 border-indigo-300 bg-gradient-to-r from-indigo-50 to-white p-3 shadow-sm dark:border-indigo-700 dark:from-indigo-950/40 dark:to-gray-900">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" wire:click="previousWeek" class="inline-flex items-center rounded-lg border-2 border-indigo-400 bg-white px-3 py-1.5 text-xs font-bold text-indigo-800 shadow-sm hover:bg-indigo-100 dark:border-indigo-500 dark:bg-gray-900 dark:text-indigo-200 dark:hover:bg-indigo-950">
                    ◀ 前週
                </button>
                <span class="text-sm font-bold tabular-nums text-gray-900 dark:text-white">{{ $this->weekRangeLabel }}</span>
                <button type="button" wire:click="nextWeek" class="inline-flex items-center rounded-lg border-2 border-indigo-400 bg-white px-3 py-1.5 text-xs font-bold text-indigo-800 shadow-sm hover:bg-indigo-100 dark:border-indigo-500 dark:bg-gray-900 dark:text-indigo-200 dark:hover:bg-indigo-950">
                    次週 ▶
                </button>
                <span class="ms-1 inline-flex items-center gap-1.5">
                    <span class="whitespace-nowrap text-[11px] font-semibold text-gray-600 dark:text-gray-400">週開始</span>
                    <x-filament::input.wrapper
                        class="w-[4.75rem] shrink-0 rounded-lg border-2 border-indigo-300 bg-white py-0 shadow-sm dark:border-indigo-600 dark:bg-gray-900"
                    >
                        <x-filament::input.select
                            wire:model.live.debounce.500ms="startDayOfWeek"
                            class="!py-1 !pe-6 !ps-2 !text-xs !font-bold text-indigo-900 dark:text-indigo-100"
                        >
                            <option value="0">日</option>
                            <option value="1">月</option>
                            <option value="2">火</option>
                            <option value="3">水</option>
                            <option value="4">木</option>
                            <option value="5">金</option>
                            <option value="6">土</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </span>
            </div>
            <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 dark:text-gray-300">
                <span class="whitespace-nowrap">月へ移動</span>
                <input type="month" wire:model.live="month_picker" class="fi-input rounded-lg border-2 border-gray-400 bg-white text-sm font-medium dark:border-gray-600 dark:bg-gray-900" />
            </label>
        </div>
        <p class="mt-2 text-[11px] leading-tight text-gray-600 dark:text-gray-400">
            現金支払いの目安：曜日ごとの合計（ランチ＋ディナー）。メモは各配分の編集画面で入力できます。
        </p>
    </div>

    <div class="grid min-h-0 gap-4 xl:grid-cols-5">
        {{-- 左：週間マトリックス --}}
        <div class="flex min-h-0 flex-col xl:col-span-3">
            <div class="mb-1 flex items-center justify-between gap-2">
                <h3 class="text-xs font-bold uppercase tracking-wide text-indigo-800 dark:text-indigo-300">週間：誰がいついくら</h3>
                <span class="rounded-md border border-indigo-200 bg-indigo-100 px-2 py-0.5 text-xs font-bold tabular-nums text-indigo-900 dark:border-indigo-800 dark:bg-indigo-950 dark:text-indigo-200">
                    週計 {{ number_format($w['week_total'], 3) }} TND
                </span>
            </div>
            <div class="max-h-[min(58vh,32rem)] overflow-auto rounded-xl border-2 border-indigo-400 bg-white shadow-md dark:border-indigo-600 dark:bg-gray-950">
                <table class="w-full min-w-[25rem] border-collapse text-xs sm:min-w-[27rem]">
                    <thead class="sticky top-0 z-10 shadow-sm">
                        <tr class="border-b-2 border-indigo-600 bg-indigo-100 dark:border-indigo-500 dark:bg-indigo-950">
                            <th class="sticky left-0 z-20 max-w-[5rem] border-r-2 border-indigo-300 bg-indigo-100 px-1.5 py-1.5 text-left text-[10px] font-bold leading-tight text-indigo-950 dark:border-indigo-700 dark:bg-indigo-950 dark:text-white sm:max-w-none sm:px-2 sm:text-xs">スタッフ</th>
                            @foreach($w['day_labels'] as $i => $label)
                                <th class="border-l border-indigo-200 px-0.5 py-1.5 text-center text-[10px] font-bold leading-none text-indigo-900 dark:border-indigo-800 dark:text-indigo-100 sm:px-1 sm:text-xs">{{ $label }}</th>
                            @endforeach
                            <th class="border-l-2 border-indigo-400 bg-indigo-200/80 px-1 py-1.5 text-center text-[10px] font-bold text-indigo-950 dark:border-indigo-600 dark:bg-indigo-900 dark:text-white sm:px-2 sm:text-xs">週計</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($w['rows'] as $ri => $row)
                            <tr class="{{ $ri % 2 === 0 ? 'bg-white dark:bg-gray-950' : 'bg-slate-50 dark:bg-gray-900/80' }} border-b border-gray-200 dark:border-gray-700">
                                <td class="sticky left-0 z-10 max-w-[5rem] border-r-2 border-gray-300 bg-inherit px-1.5 py-1 text-[10px] font-semibold leading-tight text-gray-900 dark:border-gray-600 dark:text-white sm:max-w-none sm:px-2 sm:py-1.5 sm:text-xs">
                                    <a href="{{ $this->staffEditUrl($row['staff_id']) }}" class="text-primary-600 hover:underline dark:text-primary-400" wire:navigate>
                                        {{ $row['name'] }}
                                    </a>
                                </td>
                                @foreach($row['days'] as $cell)
                                    @php
                                        $lRaw = (float) $cell['lunch_amount'];
                                        $dRaw = (float) $cell['dinner_amount'];
                                        $tRaw = (float) $cell['amount'];
                                        $hasNote = filled($cell['note_hint']);
                                    @endphp
                                    <td class="border-l border-gray-200 px-0.5 py-0.5 text-center align-middle dark:border-gray-700">
                                        <div
                                            class="mx-auto flex w-full min-w-[2.8rem] max-w-[3.75rem] flex-col gap-[2px] rounded border px-1 py-0.5 font-mono text-[10px] leading-none tabular-nums sm:min-w-[3rem] sm:max-w-none {{ $tRaw > 0 ? 'border-amber-200/90 bg-amber-50/60 dark:border-amber-800/70 dark:bg-amber-950/35' : 'border-gray-200/80 bg-gray-50/50 dark:border-gray-700 dark:bg-gray-900/40' }}"
                                            @if($hasNote) title="{{ $cell['note_hint'] }}" @endif
                                        >
                                            <div class="flex items-center justify-between gap-0.5">
                                                <span class="shrink-0 text-[8px] leading-none opacity-70" aria-hidden="true">☀️</span>
                                                <span class="min-w-0 text-end {{ $lRaw > 0 ? 'font-bold text-amber-600 dark:text-amber-400' : 'font-medium text-gray-300 dark:text-gray-600' }}">{{ $tipSmart($lRaw) }}</span>
                                            </div>
                                            <div class="flex items-center justify-between gap-0.5">
                                                <span class="shrink-0 text-[8px] leading-none opacity-70" aria-hidden="true">🌙</span>
                                                <span class="min-w-0 text-end {{ $dRaw > 0 ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'font-medium text-gray-300 dark:text-gray-600' }}">{{ $tipSmart($dRaw) }}</span>
                                            </div>
                                            <div class="mt-0.5 flex items-center justify-between gap-0.5 border-t border-gray-300 pt-0.5 dark:border-gray-600">
                                                <span class="shrink-0 text-[8px] font-black leading-none text-gray-800 dark:text-gray-200">Σ</span>
                                                <span class="inline-flex min-w-0 items-center justify-end gap-0.5 {{ $tRaw > 0 ? 'font-black text-emerald-700 dark:text-emerald-400' : 'font-medium text-gray-300 dark:text-gray-600' }}">
                                                    <span class="text-end tabular-nums">{{ $tipSmart($tRaw) }}</span>
                                                    @if($hasNote)
                                                        <span class="shrink-0 text-[8px] opacity-80" aria-hidden="true">📝</span>
                                                    @endif
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                @endforeach
                                <td class="border-l-2 border-indigo-300 bg-indigo-50/80 px-1 py-1 text-end align-middle font-mono text-[10px] font-bold tabular-nums text-indigo-900 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-100 sm:px-2 sm:text-xs">
                                    @php $wt = (float) $row['week_total']; @endphp
                                    <span class="{{ $wt > 0 ? '' : 'text-gray-300 dark:text-gray-600' }}">{{ $tipSmart($wt) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($w['day_labels']) + 2 }}" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    この週に登録されたチップ配分はありません。「チップ計算」から登録するか、別週を選んでください。
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- 右：月間 --}}
        <div class="flex min-h-0 flex-col gap-3 xl:col-span-2">
            <div class="rounded-xl border-2 border-emerald-400 bg-gradient-to-br from-emerald-50 to-white p-3 shadow-sm dark:border-emerald-700 dark:from-emerald-950/30 dark:to-gray-900">
                <div class="text-[10px] font-bold uppercase tracking-wide text-emerald-800 dark:text-emerald-300">{{ $m['month_label'] }} · 店舗チップ総額</div>
                <div class="mt-1 text-2xl font-black tabular-nums text-emerald-700 dark:text-emerald-400">{{ number_format($m['pool'], 3) }} <span class="text-sm font-semibold">TND</span></div>
            </div>

            <div class="flex min-h-0 flex-1 flex-col rounded-xl border-2 border-violet-400 bg-white p-3 shadow-md dark:border-violet-700 dark:bg-gray-950">
                <h3 class="mb-2 text-xs font-bold uppercase tracking-wide text-violet-800 dark:text-violet-300">スタッフ別（今月の受取）</h3>
                <div class="max-h-[min(42vh,22rem)] space-y-2 overflow-y-auto pr-1">
                    @forelse($m['staff_bars'] as $i => $bar)
                        @php $c = $barColors[$i % count($barColors)]; @endphp
                        <div class="rounded-lg border border-gray-200 bg-slate-50 p-2 dark:border-gray-700 dark:bg-gray-900/80">
                            <div class="mb-1 flex items-center justify-between gap-2 text-[11px] font-semibold">
                                <a href="{{ $this->staffEditUrl($bar['staff_id']) }}" class="truncate text-violet-700 hover:underline dark:text-violet-300" wire:navigate>{{ $bar['name'] }}</a>
                                <span class="shrink-0 font-mono tabular-nums text-gray-900 dark:text-white">{{ number_format($bar['total'], 3) }}</span>
                            </div>
                            <div class="h-3 w-full overflow-hidden rounded-full border border-gray-300 bg-gray-200 dark:border-gray-600 dark:bg-gray-800">
                                <div class="{{ $c }} h-full rounded-full transition-all duration-300" style="width: {{ $bar['pct'] }}%"></div>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-gray-500 dark:text-gray-400">今月の配分データがありません。</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
