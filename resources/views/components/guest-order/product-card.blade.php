{{--
    Single product card.
    Props:
      $item – array with keys: id, name, description, image, from_price_minor, styles[], toppings[], rules{}
--}}
@props(['item'])

@php
    $styleCount = count($item['styles'] ?? []);
    $priceFormatted = \App\Support\MenuItemMoney::formatMinorForDisplay((int) ($item['from_price_minor'] ?? 0));
@endphp

<article
    role="button"
    tabindex="0"
    aria-label="{{ __('guest-order.add_to_order', ['total' => '']) }}"
    @click="$store.cart.openSheet('{{ $item['id'] }}')"
    @keydown.enter.prevent="$store.cart.openSheet('{{ $item['id'] }}')"
    @keydown.space.prevent="$store.cart.openSheet('{{ $item['id'] }}')"
    class="flex min-h-0 min-w-0 max-w-full cursor-pointer items-center gap-3 rounded-[length:var(--go-radius-card)] bg-white p-3 text-left text-gray-950 shadow-sm transition hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 focus-visible:ring-offset-1 focus-visible:ring-offset-white active:scale-[0.99] dark:bg-gray-900 dark:text-white dark:hover:bg-gray-800 dark:focus-visible:ring-slate-500 dark:focus-visible:ring-offset-gray-900"
>
    {{-- Thumbnail --}}
    <div class="h-20 w-20 max-w-full shrink-0 overflow-hidden rounded-xl bg-slate-100 dark:bg-gray-800">
        @if (!empty($item['image']))
            <img
                src="{{ $item['image'] }}"
                alt="{{ $item['name'] }}"
                class="w-full h-full object-cover"
                loading="lazy"
                onerror="this.style.display='none'"
            >
        @endif
    </div>

    {{-- Info --}}
    <div class="flex-1 min-w-0">
        <h3 class="line-clamp-2 text-sm font-semibold leading-tight text-slate-900 dark:text-white">
            {{ $item['name'] }}
        </h3>

        @if (!empty($item['description']))
            <p class="mt-0.5 line-clamp-2 text-xs leading-snug text-slate-500 dark:text-slate-300">
                {{ $item['description'] }}
            </p>
        @endif

        @if ($styleCount > 0)
            <span class="mt-1.5 inline-block rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600 dark:bg-gray-800 dark:text-slate-200">
                {{ trans_choice('guest-order.styles_badge', $styleCount, ['count' => $styleCount]) }}
            </span>
        @endif
    </div>

    {{-- Price + add icon (card is the click target) --}}
    <div class="flex flex-col items-end gap-2 shrink-0">
        <span class="text-sm font-semibold tabular-nums text-slate-900 dark:text-white">
            {{ $priceFormatted }}
        </span>

        <span
            class="pointer-events-none flex h-8 w-8 max-w-full items-center justify-center rounded-full border border-slate-300 bg-white text-slate-500 dark:border-slate-600 dark:bg-gray-800 dark:text-slate-200"
            aria-hidden="true"
        >
            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="8" y1="2" x2="8" y2="14"/>
                <line x1="2" y1="8" x2="14" y2="8"/>
            </svg>
        </span>
    </div>
</article>
