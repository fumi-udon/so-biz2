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
    class="flex items-center gap-3 bg-white rounded-[length:var(--go-radius-card)] shadow-sm p-3"
>
    {{-- Thumbnail --}}
    <div class="w-20 h-20 shrink-0 overflow-hidden rounded-xl bg-slate-100">
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
        <h3 class="text-sm font-semibold text-slate-900 leading-tight line-clamp-2">
            {{ $item['name'] }}
        </h3>

        @if (!empty($item['description']))
            <p class="mt-0.5 text-xs text-slate-500 leading-snug line-clamp-2">
                {{ $item['description'] }}
            </p>
        @endif

        @if ($styleCount > 0)
            <span class="mt-1.5 inline-block px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[11px] font-medium">
                {{ trans_choice('guest-order.styles_badge', $styleCount, ['count' => $styleCount]) }}
            </span>
        @endif
    </div>

    {{-- Price + add button --}}
    <div class="flex flex-col items-end gap-2 shrink-0">
        <span class="text-sm font-semibold text-slate-900 tabular-nums">
            {{ $priceFormatted }}
        </span>

        <button
            type="button"
            @click="$store.cart.openSheet('{{ $item['id'] }}')"
            class="w-8 h-8 rounded-full flex items-center justify-center text-slate-500 border border-slate-300 bg-white hover:bg-slate-50 active:scale-95 transition focus:outline-none focus:ring-2 focus:ring-offset-1"
            :style="'focus-ring-color: var(--go-accent);'"
            aria-label="{{ __('guest-order.add_to_order', ['total' => '']) }}"
        >
            <svg class="w-4 h-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <line x1="8" y1="2" x2="8" y2="14"/>
                <line x1="2" y1="8" x2="14" y2="8"/>
            </svg>
        </button>
    </div>
</article>
