{{-- POS kiosk footer: low-frequency actions (change table, admin, logout). Used by table-dashboard. --}}
<div
    class="relative shrink-0"
    x-data="{ posUtilityMenuOpen: false }"
    x-on:keydown.escape.window="posUtilityMenuOpen = false"
    x-on:click.outside="posUtilityMenuOpen = false"
>
    <button
        type="button"
        class="inline-flex h-11 w-11 shrink-0 touch-manipulation items-center justify-center rounded-md border border-slate-200 bg-white text-gray-500 shadow-sm transition hover:border-slate-300 hover:bg-slate-50 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 active:scale-95 dark:border-slate-600 dark:bg-gray-900 dark:text-gray-400 dark:hover:border-slate-500 dark:hover:bg-slate-800 dark:hover:text-gray-200"
        x-on:click.stop="posUtilityMenuOpen = ! posUtilityMenuOpen"
        x-bind:aria-expanded="posUtilityMenuOpen ? 'true' : 'false'"
        aria-haspopup="menu"
        aria-label="{{ __('pos.footer_utility_menu') }}"
    >
        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" />
        </svg>
    </button>

    <div
        x-cloak
        x-show="posUtilityMenuOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[205] bg-slate-950/40 max-md:block md:hidden"
        x-on:click="posUtilityMenuOpen = false"
        aria-hidden="true"
    ></div>

    <div
        x-cloak
        x-show="posUtilityMenuOpen"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed left-2 right-2 bottom-2 z-[210] flex max-h-[min(70dvh,28rem)] flex-col overflow-hidden rounded-xl border border-slate-200 bg-white text-gray-950 shadow-xl max-md:flex dark:border-slate-600 dark:bg-gray-900 dark:text-gray-100 md:absolute md:bottom-full md:left-0 md:right-auto md:top-auto md:mb-1 md:max-h-none md:w-56 md:rounded-lg md:shadow-lg"
        role="menu"
        aria-label="{{ __('pos.footer_utility_menu') }}"
        x-on:click.stop
    >
        <div class="flex max-h-[min(70dvh,28rem)] min-h-0 flex-col gap-0.5 overflow-y-auto overscroll-contain p-1.5 md:max-h-none">
            <button
                type="button"
                role="menuitem"
                class="flex w-full touch-manipulation items-center gap-2 rounded-lg border border-transparent px-3 py-2.5 text-left text-sm font-semibold text-gray-950 hover:border-slate-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-gray-100 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                x-on:click="
                    posUtilityMenuOpen = false;
                    if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                        window.Livewire.dispatch('pos-tile-interaction-ended');
                    }
                "
            >
                <svg class="h-5 w-5 shrink-0 text-slate-600 dark:text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M8 7h12M8 12h12M8 17h12M4 7h.01M4 12h.01M4 17h.01" />
                </svg>
                <span class="min-w-0">{{ __('pos.action_changer_table') }}</span>
            </button>

            <a
                href="{{ url('/admin') }}"
                role="menuitem"
                class="flex w-full touch-manipulation items-center gap-2 rounded-lg border border-transparent px-3 py-2.5 text-left text-sm font-semibold text-gray-950 hover:border-slate-200 hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 dark:text-gray-100 dark:hover:border-slate-600 dark:hover:bg-slate-800"
                x-on:click="posUtilityMenuOpen = false"
            >
                <svg class="h-5 w-5 shrink-0 text-slate-600 dark:text-slate-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.37.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.377-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
                    />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                <span class="min-w-0">{{ __('pos.kiosk_open_admin') }}</span>
            </a>

            <form
                x-ref="posFooterLogoutForm"
                method="post"
                action="{{ filament()->getLogoutUrl() }}"
                role="none"
            >
                @csrf
                <button
                    type="button"
                    role="menuitem"
                    class="flex w-full touch-manipulation items-center gap-2 rounded-lg border border-transparent px-3 py-2.5 text-left text-sm font-semibold text-gray-950 hover:border-rose-200 hover:bg-rose-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-400 dark:text-gray-100 dark:hover:border-rose-900/60 dark:hover:bg-rose-950/30"
                    x-on:click="if (confirm(@js(__('pos.logout_confirm')))) { $refs.posFooterLogoutForm.requestSubmit(); }"
                >
                    <svg class="h-5 w-5 shrink-0 text-rose-600 dark:text-rose-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9" />
                    </svg>
                    <span class="min-w-0">{{ __('pos.action_logout') }}</span>
                </button>
            </form>
        </div>
    </div>
</div>
