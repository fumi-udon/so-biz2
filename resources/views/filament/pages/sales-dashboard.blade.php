<x-filament-panels::page>
    <div class="space-y-1 [&_.fi-ta-header-cell]:!px-1 [&_.fi-ta-header-cell]:!py-1 [&_.fi-ta-cell]:!px-1 [&_.fi-ta-cell]:!py-1 [&_.fi-ta-header-cell-label]:!text-[11px] [&_.fi-ta-text-item-label]:!text-[11px]">
        <div class="rounded-md border border-sky-300 bg-sky-50 px-2 py-1 text-[11px] font-semibold text-sky-900 dark:border-sky-700 dark:bg-sky-950/40 dark:text-sky-100">
            日計売上（ランチ / ディナー = 日）
        </div>
        {{ $this->table }}
    </div>
</x-filament-panels::page>
