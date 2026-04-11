<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div
        class="relative overflow-hidden rounded-2xl border-2 border-sky-300/70 bg-gradient-to-b from-sky-400 via-sky-200 to-sky-100 p-3 shadow-xl dark:border-sky-800/60 dark:from-slate-900 dark:via-sky-950 dark:to-slate-950 sm:p-5"
    >
        <div
            class="pointer-events-none absolute -left-6 top-6 h-14 w-24 rounded-full bg-white/50 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute right-8 top-10 h-10 w-20 rounded-full bg-white/40 blur-sm dark:bg-white/10"
            aria-hidden="true"
        ></div>
        <div
            class="pointer-events-none absolute bottom-16 right-1/4 h-12 w-32 rounded-full bg-white/30 blur-sm dark:bg-white/5"
            aria-hidden="true"
        ></div>

        <div
            class="relative flex flex-col gap-y-4 rounded-2xl border-2 border-b-4 border-white/90 bg-white/95 p-3 shadow-xl dark:border-slate-600/80 dark:bg-slate-900/95 sm:gap-y-6 sm:p-4"
        >
            <x-filament-panels::resources.tabs />

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE, scopes: $this->getRenderHookScopes()) }}

            {{ $this->table }}

            {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER, scopes: $this->getRenderHookScopes()) }}
        </div>
    </div>
</x-filament-panels::page>
