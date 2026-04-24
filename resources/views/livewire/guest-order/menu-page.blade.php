@php
    $categoryIds = array_column($catalog['categories'] ?? [], 'id');
    $guestOrderPayload = [
        'catalog'        => $catalog,
        'translations'   => $translations,
        'categoryIds'    => $categoryIds,
        'context'        => [
            'tenantSlug' => $tenantSlug,
            'tableToken' => $tableToken,
        ],
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

    Livewire 3: the framework injects wire:id into this view’s *first* HTML tag
    (Livewire\Drawer\Utils::insertAttributesIntoHtmlRoot). That tag MUST be this
    root div so #guest-order-root carries wire:id (order-store.js relies on it).
    Do not place <script> or other tags before this opening div.
--}}
<div
    id="guest-order-root"
    x-init="$store.cart.bootstrapFromDom()"
    class="flex min-h-[100dvh] min-h-screen max-w-full flex-col overflow-x-hidden bg-[color:var(--go-surface)] text-gray-950 dark:bg-gray-950 dark:text-white"
>
    <script type="application/json" id="guest-order-data">{!! json_encode($guestOrderPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR) !!}</script>
    <script>
        window.sharedMenuData = @js(collect($catalog['categories'] ?? [])->pluck('items')->flatten(1)->values()->all());
    </script>
    <div class="pointer-events-none fixed inset-0 z-80">
        <template x-for="fx in $store.cart.flyEffects" :key="fx.id">
            <span
                class="absolute rounded-full"
                :style="`
                    left:${fx.startX}px;
                    top:${fx.startY}px;
                    width:${fx.size}px;
                    height:${fx.size}px;
                    transform: translate(-50%, -50%)
                        translate(${fx.active ? fx.dx : 0}px, ${fx.active ? fx.dy : 0}px)
                        scale(${fx.active ? 0.2 : 1})
                        rotate(${fx.active ? fx.rotate : 0}deg);
                    opacity:${fx.active ? 0 : 1};
                    background: radial-gradient(circle at 35% 35%, #ffffff 0%, #bfdbfe 30%, #2563eb 100%);
                    box-shadow:
                        0 0 ${fx.glow}px rgba(37, 99, 235, 0.95),
                        0 0 ${fx.glow * 2}px rgba(56, 189, 248, 0.7),
                        0 0 ${fx.glow * 3}px rgba(59, 130, 246, 0.45);
                    transition:
                        transform ${fx.duration}ms cubic-bezier(0.16, 0.84, 0.29, 1),
                        opacity ${fx.duration}ms ease-out;
                `"
            ></span>
        </template>
    </div>

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <header class="flex min-w-0 shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-3 text-gray-950 dark:border-slate-700 dark:bg-gray-900 dark:text-white">
        <a href="javascript:history.back()" class="text-slate-500 hover:text-slate-800 focus:outline-none dark:text-slate-400 dark:hover:text-slate-200" aria-label="Retour">
            <svg class="w-5 h-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <polyline points="13,4 7,10 13,16"/>
            </svg>
        </a>
        <div class="flex min-w-0 flex-1 items-center justify-center gap-2">
            <img
                src="{{ $theme['logo_url'] }}"
                alt="{{ $theme['display_name'] }} logo"
                class="h-7 w-auto max-w-[min(10rem,42vw)] shrink object-contain"
                onerror="this.style.display='none'"
            >
            <span class="truncate text-base font-bold tracking-tight text-slate-900 dark:text-white">{{ $theme['display_name'] }}</span>
        </div>
        {{-- Spacer to balance the back arrow --}}
        <div class="w-5"></div>
    </header>

    {{-- ── Catalog + cart: column on phone, row from md (rail | menu | cart) ─ --}}
    <div class="flex min-h-0 min-w-0 flex-1 flex-col md:flex-row md:items-start">
        {{-- ── Sticky category rail (horizontal phone / vertical md+) ───────── --}}
        <x-guest-order.category-rail :categories="$catalog['categories'] ?? []" />

        {{--
            wire:ignore wraps the entire catalog so Livewire never touches
            the DOM after initial render — protects IntersectionObserver
            registrations and Alpine state from re-render diffing.
        --}}
        <main
            class="min-w-0 flex-1 pb-28 md:pb-6"
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

        {{-- ── Cart: fixed bottom bar phone / sticky sidebar md+ ─────────────── --}}
        <x-guest-order.floating-cart />
    </div>

    {{-- ── Bottom-sheet modal ──────────────────────────────────────────────── --}}
    <x-guest-order.customize-sheet />

    {{-- ── Full cart drawer (Phase 1.5) ────────────────────────────────────── --}}
    <x-guest-order.cart-panel />
</div>
