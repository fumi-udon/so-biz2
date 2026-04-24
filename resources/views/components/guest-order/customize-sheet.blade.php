{{--
    Bottom-sheet modal for customizing a product (style + toppings).
    Reads entirely from Alpine.store('cart') — no props needed.
    Safe-Area and overscroll handled here.
--}}

{{-- Backdrop --}}
<div
    x-show="$store.cart.sheetOpen"
    x-transition:enter="transition duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition duration-150"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    @click="$store.cart.closeSheet()"
    class="fixed inset-0 z-40 max-w-[100vw] bg-black/50"
    style="overscroll-behavior: none; touch-action: none;"
    aria-hidden="true"
    x-cloak
></div>

{{-- Sheet panel --}}
<div
    x-show="$store.cart.sheetOpen"
    x-transition:enter="transition duration-300 ease-out"
    x-transition:enter-start="translate-y-full"
    x-transition:enter-end="translate-y-0"
    x-transition:leave="transition duration-200 ease-in"
    x-transition:leave-start="translate-y-0"
    x-transition:leave-end="translate-y-full"
    class="fixed inset-x-0 bottom-0 z-50 flex max-h-[90dvh] max-w-full flex-col overflow-x-hidden rounded-t-2xl bg-white text-gray-950 shadow-2xl dark:bg-gray-900 dark:text-white"
    role="dialog"
    aria-modal="true"
    :aria-label="$store.cart.editingItem?.name ?? ''"
    x-cloak
>
    {{-- Drag handle --}}
    <div class="flex justify-center pt-3 pb-1 shrink-0">
        <div class="w-10 h-1 rounded-full bg-slate-300"></div>
    </div>

    {{-- Hero image --}}
    <div
        x-show="$store.cart.editingItem?.image"
        class="w-full h-32 sm:h-36 overflow-hidden shrink-0"
    >
        <img
            :src="$store.cart.editingItem?.image ?? ''"
            :alt="$store.cart.editingItem?.name ?? ''"
            class="w-full h-full object-cover"
        >
    </div>

    {{-- Close button --}}
    <button
        type="button"
        @click="$store.cart.closeSheet()"
        class="absolute top-4 right-4 z-10 w-8 h-8 rounded-full bg-black/40 flex items-center justify-center text-white focus:outline-none"
        :aria-label="$store.cart.t('close')"
    >
        <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
            <line x1="3" y1="3" x2="13" y2="13"/>
            <line x1="13" y1="3" x2="3" y2="13"/>
        </svg>
    </button>

    {{-- Scrollable content --}}
    <div
        class="flex-1 overflow-y-auto px-4 pt-3 pb-1"
        style="overscroll-behavior-y: contain; -webkit-overflow-scrolling: touch;"
    >
        {{-- Product title & description --}}
        <h2
            class="text-xl font-bold text-slate-900 leading-tight"
            x-text="$store.cart.editingItem?.name ?? ''"
        ></h2>
        <p
            x-show="$store.cart.editingItem?.description"
            class="mt-1 text-sm text-slate-500"
            x-text="$store.cart.editingItem?.description ?? ''"
        ></p>

        {{-- SELECT STYLE --}}
        <div
            x-show="($store.cart.editingItem?.styles?.length ?? 0) > 0"
            class="mt-4"
        >
            <p class="mb-2 text-xs font-bold tracking-widest text-slate-700 uppercase">
                <span x-text="$store.cart.t('select_style')"></span>
                <span
                    x-show="$store.cart.editingItem?.rules?.style_required"
                    class="ml-1 text-(--go-danger) font-bold"
                >*</span>
            </p>

            <div class="flex flex-col gap-1.5">
                <template x-for="style in ($store.cart.editingItem?.styles ?? [])" :key="style.id">
                    <label
                        class="flex items-center justify-between gap-3 border rounded-xl px-3 py-2.5 cursor-pointer transition"
                        :class="$store.cart.selectedStyleId === style.id
                            ? 'border-(--go-primary) bg-blue-50/60'
                            : 'border-slate-200 hover:border-slate-300 bg-white'"
                    >
                        <span class="flex items-center gap-3 min-w-0">
                            <span
                                class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0 transition"
                                :class="$store.cart.selectedStyleId === style.id
                                    ? 'border-(--go-primary)'
                                    : 'border-slate-300'"
                            >
                                <span
                                    x-show="$store.cart.selectedStyleId === style.id"
                                    class="w-2.5 h-2.5 rounded-full"
                                    :style="'background-color: var(--go-primary);'"
                                ></span>
                            </span>
                            <span class="text-sm text-slate-800 truncate" x-text="style.name"></span>
                        </span>
                        <span
                            class="text-sm font-semibold tabular-nums shrink-0"
                            :class="$store.cart.selectedStyleId === style.id
                                ? 'text-slate-900'
                                : 'text-slate-500'"
                            x-text="$store.cart.formatMinorToDisplay(style.price_minor)"
                        ></span>
                        <input
                            type="radio"
                            class="sr-only"
                            :value="style.id"
                            :checked="$store.cart.selectedStyleId === style.id"
                            @change="$store.cart.selectedStyleId = style.id"
                        >
                    </label>
                </template>
            </div>

            {{-- Validation hint --}}
            <p
                x-show="!$store.cart.canAddToCart() && $store.cart.editingItem?.rules?.style_required"
                class="mt-2 text-xs font-medium text-(--go-danger)"
                x-text="$store.cart.t('please_select_style')"
            ></p>
        </div>

        {{-- TOPPINGS --}}
        <div
            x-show="($store.cart.editingItem?.toppings?.length ?? 0) > 0"
            class="mt-4"
        >
            <p class="mb-2 text-xs font-bold tracking-widest text-slate-700 uppercase"
               x-text="$store.cart.t('toppings')">
            </p>

            <div class="flex flex-col gap-1">
                <template x-for="topping in ($store.cart.editingItem?.toppings ?? [])" :key="topping.id">
                    <label
                        class="flex items-center justify-between gap-3 px-1 py-1.5 cursor-pointer"
                    >
                        <span class="flex items-center gap-3 min-w-0">
                            <span
                                class="w-5 h-5 rounded border-2 flex items-center justify-center shrink-0 transition"
                                :class="$store.cart.selectedToppingIds.includes(topping.id)
                                    ? 'border-(--go-primary) bg-(--go-primary)'
                                    : 'border-slate-300 bg-white'"
                            >
                                <svg
                                    x-show="$store.cart.selectedToppingIds.includes(topping.id)"
                                    class="w-3 h-3 text-white"
                                    viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"
                                >
                                    <polyline points="1.5,6 4.5,9 10.5,3"/>
                                </svg>
                            </span>
                            <span class="text-sm text-slate-800" x-text="topping.name"></span>
                        </span>
                        <span
                            class="text-sm text-slate-500 tabular-nums shrink-0"
                            x-text="'+ ' + $store.cart.formatMinorToDisplay(topping.price_delta_minor)"
                        ></span>
                        <input
                            type="checkbox"
                            class="sr-only"
                            :value="topping.id"
                            :checked="$store.cart.selectedToppingIds.includes(topping.id)"
                            @change="$store.cart.toggleTopping(topping.id)"
                        >
                    </label>
                </template>
            </div>
        </div>

        {{-- Spacer for CTA --}}
        <div class="h-3"></div>
    </div>

    {{-- CTA Bar (sticky bottom, safe-area aware) --}}
    <div
        class="shrink-0 px-4 pt-2 bg-white border-t border-slate-100"
        style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));"
    >
        <button
            type="button"
            @click="$store.cart.addToCart($event)"
            :disabled="!$store.cart.canAddToCart()"
            class="w-full py-3.5 rounded-(--go-radius-button) text-sm font-bold tracking-wide transition active:scale-[0.98] focus:outline-none disabled:opacity-50 disabled:cursor-not-allowed"
            :style="'background-color: var(--go-primary); color: var(--go-on-primary);'"
            x-text="$store.cart.t('add_to_order', { total: $store.cart.formatMinorToDisplay($store.cart.sheetUnitTotalMinor()) })"
        ></button>
    </div>
</div>
