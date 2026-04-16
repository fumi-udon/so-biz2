@php
    $categoryIds = array_column($catalog['categories'] ?? [], 'id');
    $guestOrderPayload = [
        'catalog'       => $catalog,
        'translations'    => $translations,
        'categoryIds'     => $categoryIds,
    ];
@endphp

{{--
    Inject tenant brand tokens as :root CSS custom properties.
    The layout fallback (Bistro Nippon) is overridden here.
--}}
@push('guest-theme')
<style>
    :root {
        --go-primary:        {{ $theme['primary_hex'] }};
        --go-on-primary:     {{ $theme['on_primary_hex'] }};
        --go-accent:         {{ $theme['accent_hex'] }};
        --go-danger:         {{ $theme['danger_hex'] }};
        --go-surface:        {{ $theme['surface_hex'] }};
        --go-cart-bg:        {{ $theme['cart_bg_hex'] }};
        --go-radius-button:  {{ $theme['button_radius_rem'] }};
        --go-radius-card:    {{ $theme['card_radius_rem'] }};
        --go-font:           {{ $theme['font_family'] }};
    }
</style>
@endpush

@push('guest-font')
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="{{ $theme['font_url'] }}" rel="stylesheet">
@endpush

{{--
    Hydration Standard: no @json inside HTML attributes.
    Payload lives in <script type="application/json"> only.
    x-init calls a single store method — no quoted JSON in attributes.
--}}
<script type="application/json" id="guest-order-data">{!! json_encode($guestOrderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>

<div
    id="guest-order-root"
    x-init="$store.cart.bootstrapFromDom()"
>
    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <header class="flex items-center gap-3 px-4 py-3 bg-white border-b border-slate-200">
        <a href="javascript:history.back()" class="text-slate-500 hover:text-slate-800 focus:outline-none" aria-label="Retour">
            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="13,4 7,10 13,16"/>
            </svg>
        </a>
        <div class="flex-1 flex items-center justify-center gap-2">
            <img
                src="{{ $theme['logo_url'] }}"
                alt="{{ $theme['display_name'] }} logo"
                class="h-7 w-auto"
                onerror="this.style.display='none'"
            >
            <span class="text-base font-bold text-slate-900 tracking-tight">{{ $theme['display_name'] }}</span>
        </div>
        {{-- Spacer to balance the back arrow --}}
        <div class="w-5"></div>
    </header>

    {{-- ── Sticky category rail ────────────────────────────────────────────── --}}
    <x-guest-order.category-rail :categories="$catalog['categories'] ?? []" />

    {{--
        wire:ignore wraps the entire catalog so Livewire never touches
        the DOM after initial render — protects IntersectionObserver
        registrations and Alpine state from re-render diffing.
    --}}
    <main
        class="pb-28"
        wire:ignore
    >
        @foreach (($catalog['categories'] ?? []) as $category)
            <x-guest-order.category-section
                :category-id="$category['id']"
                :category-label="$category['label']"
            >
                @foreach (($category['items'] ?? []) as $item)
                    <x-guest-order.product-card :item="$item" />
                @endforeach
            </x-guest-order.category-section>
        @endforeach
    </main>

    {{-- ── Bottom-sheet modal ──────────────────────────────────────────────── --}}
    <x-guest-order.customize-sheet />

    {{-- ── Floating cart bar ───────────────────────────────────────────────── --}}
    <x-guest-order.floating-cart />
</div>
