{{--
    Compact cart sheet — simple mobile-first layout (reference: clear header + lines + Commander).
    State: Alpine.store('cart'). Ver2: submit → table POS API (see buildTransmissionDraft).
--}}

{{-- Backdrop --}}
<div
    x-show="$store.cart.cartPanelOpen"
    x-transition:enter="transition duration-200 ease-out"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition duration-150 ease-in"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="$store.cart.closeCartPanel()"
    class="fixed inset-0 z-55 max-w-[100vw] bg-black/50"
    style="overscroll-behavior: none; touch-action: none;"
    aria-hidden="true"
    x-cloak
></div>

{{-- Panel --}}
<div
    x-show="$store.cart.cartPanelOpen"
    x-transition:enter="transition duration-280 ease-out"
    x-transition:enter-start="translate-y-full"
    x-transition:enter-end="translate-y-0"
    x-transition:leave="transition duration-200 ease-in"
    x-transition:leave-start="translate-y-0"
    x-transition:leave-end="translate-y-full"
    @keydown.escape.window="$store.cart.cartPanelOpen && $store.cart.onCartPanelEscape()"
    class="fixed inset-x-0 bottom-0 z-60 flex max-h-[min(92dvh,100%)] max-w-full flex-col overflow-x-hidden rounded-t-2xl bg-white text-gray-950 shadow-[0_-12px_40px_rgba(15,23,42,0.18)] dark:bg-gray-900 dark:text-white"
    style="padding-top: env(safe-area-inset-top);"
    role="dialog"
    aria-modal="true"
    :aria-label="$store.cart.t('cart_modal_title')"
    x-cloak
>
    {{-- Grab hint --}}
    <div class="flex shrink-0 justify-center pt-2 pb-1" aria-hidden="true">
        <div class="h-1 w-9 rounded-full bg-slate-200"></div>
    </div>

    {{-- Header: title | total | close (reference-style) --}}
    <header class="shrink-0 border-b border-slate-200 px-4 pb-2.5 pt-0.5">
        <div class="flex items-center gap-3">
            <h2 class="min-w-0 flex-1 text-lg font-bold leading-none text-slate-950" x-text="$store.cart.t('cart_modal_title')"></h2>
            <div class="flex shrink-0 items-center gap-2">
                <span
                    id="guest-cart-fx-target"
                    class="text-base font-bold tabular-nums text-slate-950 transition duration-300"
                    :class="$store.cart.panelPulseTotal ? 'text-(--go-primary) scale-105' : ''"
                    x-text="$store.cart.formatMinorToDisplay($store.cart.cartTotalMinor())"
                ></span>
                <button
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 transition hover:bg-slate-200 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    @click="$store.cart.closeCartPanel()"
                    :aria-label="$store.cart.t('close')"
                >
                    <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                        <line x1="3" y1="3" x2="13" y2="13" />
                        <line x1="13" y1="3" x2="3" y2="13" />
                    </svg>
                </button>
            </div>
        </div>
        <p
            x-show="$store.cart.lines.length > 0"
            class="mt-1.5 line-clamp-2 text-[11px] leading-snug text-slate-500"
            x-text="$store.cart.t('order_flow_notice')"
        ></p>
    </header>

    {{-- List --}}
    <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain [-webkit-overflow-scrolling:touch] px-4">
        {{-- Empty --}}
        <div
            x-show="$store.cart.lines.length === 0"
            class="flex flex-col items-center gap-2 py-12 text-center"
        >
            <p class="text-sm font-medium text-slate-600" x-text="$store.cart.t('empty_cart')"></p>
            <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold text-white shadow focus:outline-none focus-visible:ring-2"
                :style="'background-color: var(--go-primary); color: var(--go-on-primary);'"
                @click="$store.cart.continueShopping()"
                x-text="$store.cart.t('continue_shopping')"
            ></button>
        </div>

        <div class="mx-auto max-w-lg pb-2" x-show="$store.cart.lines.length > 0">
            <template x-for="(line, index) in $store.cart.lines" :key="line.lineId">
                <div
                    class="go-cart-row-enter flex items-center gap-2 border-b border-slate-100 py-2.5 last:border-b-0"
                    :style="'animation-delay:' + (index * 24) + 'ms'"
                >
                    <button
                        type="button"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-50 hover:text-(--go-danger) focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-300"
                        @click="$store.cart.removeLine(line.lineId)"
                        :aria-label="$store.cart.t('remove_line')"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                            <line x1="3" y1="3" x2="13" y2="13" />
                            <line x1="13" y1="3" x2="3" y2="13" />
                        </svg>
                    </button>

                    <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-full bg-slate-100 ring-1 ring-slate-200/80">
                        <img
                            :src="$store.cart.resolveItemImage(line.itemId) ?? ''"
                            :alt="line.titleSnapshot"
                            class="h-full w-full object-cover"
                            x-show="$store.cart.resolveItemImage(line.itemId)"
                        >
                        <div
                            class="flex h-full w-full items-center justify-center text-[10px] font-bold text-slate-400"
                            x-show="!$store.cart.resolveItemImage(line.itemId)"
                            x-text="(line.titleSnapshot || '?').slice(0, 1).toUpperCase()"
                        ></div>
                    </div>

                    <div class="min-w-0 flex-1 py-px">
                        <p
                            class="text-sm font-semibold leading-tight text-slate-950"
                            x-text="line.qty + ' ' + line.titleSnapshot"
                        ></p>
                        <p
                            class="mt-px line-clamp-2 text-[11px] leading-tight text-slate-500"
                            x-show="$store.cart.lineModifiersSummary(line).length > 0"
                            x-text="$store.cart.lineModifiersSummary(line)"
                        ></p>
                    </div>

                    <div class="flex shrink-0 flex-col items-end gap-1">
                        <span class="text-sm font-semibold tabular-nums text-slate-950" x-text="$store.cart.formatMinorToDisplay(line.lineTotalMinor)"></span>
                        <div class="flex items-center gap-0.5 rounded-full bg-slate-100 p-0.5">
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-full text-sm font-bold text-slate-700 transition hover:bg-white active:scale-90 focus:outline-none focus-visible:ring-1 focus-visible:ring-(--go-primary)"
                                @click="$store.cart.decrementLineQty(line.lineId)"
                                :aria-label="$store.cart.t('qty') + ' −'"
                            >−</button>
                            <span class="min-w-5 text-center text-[11px] font-bold tabular-nums text-slate-900" x-text="line.qty"></span>
                            <button
                                type="button"
                                class="flex h-6 w-6 items-center justify-center rounded-full text-sm font-bold text-slate-700 transition hover:bg-white active:scale-90 focus:outline-none focus-visible:ring-1 focus-visible:ring-(--go-primary)"
                                @click="$store.cart.incrementLineQty(line.lineId)"
                                :aria-label="$store.cart.t('qty') + ' +'"
                            >+</button>
                        </div>
                    </div>
                </div>
            </template>

            <button
                type="button"
                x-show="$store.cart.lines.length > 0"
                class="mt-1 w-full py-2 text-center text-xs font-medium text-slate-400 transition hover:text-(--go-danger) focus:outline-none"
                @click="$store.cart.clearCartTap()"
                :class="$store.cart._clearCartStep === 1 ? 'animate-go-shake' : ''"
            >
                <span x-show="$store.cart._clearCartStep === 0" x-text="$store.cart.t('clear_cart')"></span>
                <span x-show="$store.cart._clearCartStep === 1" x-text="$store.cart.t('clear_cart_tap_again')"></span>
            </button>
        </div>
    </div>

    {{-- Undo (compact) --}}
    <div
        x-show="$store.cart.toast && $store.cart.toast.type === 'removed'"
        x-transition
        class="fixed inset-x-0 bottom-[calc(4.25rem+env(safe-area-inset-bottom))] z-70 flex justify-center px-4"
        x-cloak
    >
        <div class="flex max-w-lg items-center gap-2 rounded-full border border-slate-200 bg-white px-3 py-1.5 text-xs text-slate-900 shadow-lg">
            <span class="flex-1 font-medium" x-text="$store.cart.t('item_removed')"></span>
            <button
                type="button"
                class="shrink-0 rounded-full bg-slate-900 px-2.5 py-1 text-[11px] font-bold text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                @click="$store.cart.undoRemoveLine()"
                x-text="$store.cart.t('undo')"
            ></button>
        </div>
    </div>

    <div
        x-show="$store.cart.clipboardBanner"
        x-transition
        class="pointer-events-none fixed inset-x-0 top-20 z-70 flex justify-center px-4"
        x-cloak
    >
        <span class="rounded-full bg-slate-900 px-3 py-1.5 text-[11px] font-bold text-white shadow" x-text="$store.cart.t('copied')"></span>
    </div>

    {{-- Footer: Commander only --}}
    <div
        class="shrink-0 border-t border-slate-100 bg-white px-4 pt-2"
        style="padding-bottom: max(0.65rem, env(safe-area-inset-bottom));"
        x-show="$store.cart.lines.length > 0"
    >
        <button
            type="button"
            class="w-full rounded-xl py-3.5 text-sm font-bold text-white shadow-md transition active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-white disabled:cursor-not-allowed disabled:opacity-55 dark:focus-visible:ring-offset-gray-900"
            :style="'background-color: var(--go-primary); color: var(--go-on-primary); --tw-ring-color: var(--go-primary);'"
            :disabled="$store.cart.submitCelebrationOpen"
            @click="$store.cart.openSubmitCelebrationAndSubmit()"
            x-text="$store.cart.t('order_submit')"
        ></button>
    </div>

 

            <button
                type="button"
                class="mt-6 w-full rounded-xl py-3.5 text-sm font-bold text-white shadow-md transition active:scale-[0.99] focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-offset-white dark:focus-visible:ring-offset-gray-900"
                :style="'background-color: var(--go-primary); color: var(--go-on-primary); --tw-ring-color: var(--go-primary);'"
                @click="$store.cart.closeSubmitCelebration()"
                x-text="$store.cart.t('submit_celebration_ok')"
            ></button>
        </div>
    </div>
</div>
