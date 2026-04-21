{{--
    Floating cart bar — visible only when lineCount > 0.
    iOS Safe Area applied to bottom padding.
--}}

<div
    x-show="$store.cart.lineCount() > 0 && !$store.cart.cartPanelOpen"
    x-transition:enter="transition duration-200 ease-out"
    x-transition:enter-start="opacity-0 translate-y-4"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition duration-150 ease-in"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-4"
    class="fixed inset-x-0 bottom-0 z-30 px-4 pt-3"
    style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
    x-cloak
>
    <button
        id="guest-cart-cta"
        type="button"
        @click.stop="$store.cart.openCartPanel()"
        class="w-full flex items-center justify-between gap-3 px-5 py-4 rounded-(--go-radius-button) shadow-2xl text-white transition active:scale-[0.98] focus:outline-none"
        :class="$store.cart.cartGlow ? 'ring-4 ring-blue-300/60' : ''"
        :style="`
            background-color: var(--go-cart-bg);
            box-shadow: ${$store.cart.cartGlow
                ? '0 0 0 2px rgba(147,197,253,.45), 0 0 24px rgba(59,130,246,.75), 0 10px 24px rgba(15,23,42,.45)'
                : '0 12px 24px rgba(15,23,42,.45)'};
        `"
        aria-live="polite"
    >
        {{-- Left: item count badge + label --}}
        <span class="flex items-center gap-2">
            <span
                class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold"
                :style="'background-color: var(--go-primary); color: var(--go-on-primary);'"
                x-text="$store.cart.lineCount()"
            ></span>
            <span class="text-sm font-semibold" x-text="$store.cart.t('to_cart')"></span>
        </span>

        {{-- Right: total --}}
        <span class="flex items-center gap-1.5">
            <span class="text-xs font-medium text-white/70" x-text="$store.cart.t('total')"></span>
            <span
                class="text-base font-bold tabular-nums"
                x-text="$store.cart.formatMinorToDisplay($store.cart.cartTotalMinor())"
            ></span>
            <svg class="w-4 h-4 opacity-80" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="5,3 11,8 5,13"/>
            </svg>
        </span>
    </button>
</div>
