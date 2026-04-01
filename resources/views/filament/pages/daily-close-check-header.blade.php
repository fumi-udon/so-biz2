<header class="fi-header flex flex-col gap-y-1">
    @if (filament()->hasBreadcrumbs() && count($this->getBreadcrumbs()))
        <x-filament::breadcrumbs
            :breadcrumbs="$this->getBreadcrumbs()"
            class="mb-0 hidden sm:block"
        />
    @endif

    {{-- ビジネス×カプコン：極薄・縦密・黄アクセント --}}
    <div
        class="flex max-w-md flex-col gap-0 border-s-4 border-amber-500 bg-gray-50/80 py-1 pe-2 ps-2.5 dark:bg-white/5"
    >
        <div class="flex items-center gap-1.5">
            <x-filament::icon
                icon="heroicon-o-calculator"
                class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400"
            />
            <span class="text-xs font-black tracking-tight text-gray-900 dark:text-white">レジ締</span>
        </div>
        <p class="pl-5 text-[10px] font-medium tabular-nums leading-none text-gray-500 dark:text-gray-400">
            {{ $this->businessDateStr }}
        </p>
    </div>
</header>
