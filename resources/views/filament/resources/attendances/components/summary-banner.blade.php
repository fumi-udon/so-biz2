@php
    $h = intdiv($stats['total_minutes'], 60);
    $m = $stats['total_minutes'] % 60;
@endphp

<div class="fi-resource-summary mb-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/80">
    <p class="mb-3 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
        フィルター条件の集計（給与・勤怠チェック用）
    </p>
    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-white/5 dark:bg-white/5">
            <div class="text-[11px] text-gray-500 dark:text-gray-400">月間労働時間（合計）</div>
            <div class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">
                {{ $h }}時間 {{ sprintf('%02d', $m) }}分
            </div>
            <div class="mt-0.5 text-xs tabular-nums text-gray-500 dark:text-gray-400">
                小数: {{ number_format($stats['total_hours_decimal'], 2, '.', '') }} h
            </div>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-white/5 dark:bg-white/5">
            <div class="text-[11px] text-gray-500 dark:text-gray-400">遅刻回数（日）</div>
            <div class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">
                {{ $stats['late_count'] }} <span class="text-sm font-normal">回</span>
            </div>
            <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                遅刻計 {{ $stats['late_total_minutes'] }} 分
            </div>
        </div>
        <div class="rounded-lg border border-gray-100 bg-gray-50/80 px-3 py-2.5 dark:border-white/5 dark:bg-white/5">
            <div class="text-[11px] text-gray-500 dark:text-gray-400">対象行数（日）</div>
            <div class="mt-1 text-lg font-semibold tabular-nums text-gray-950 dark:text-white">
                {{ $stats['day_count'] }} <span class="text-sm font-normal">件</span>
            </div>
        </div>
        <div class="rounded-lg border border-dashed border-gray-200 px-3 py-2.5 text-[11px] leading-snug text-gray-500 dark:border-white/10 dark:text-gray-400">
            各行の「当日労働」「時間(小数)」は1日分です。スタッフ・表示月で絞り込むと上記がその範囲の合計になります。
        </div>
    </div>
</div>
