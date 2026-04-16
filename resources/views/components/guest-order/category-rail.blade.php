{{--
    Category sticky tab rail.
    Props:
      $categories  – array of ['id' => string, 'label' => string]
--}}
@props(['categories'])

<nav
    class="sticky top-0 z-30 bg-white/95 backdrop-blur-sm border-b border-slate-200"
    aria-label="{{ __('guest-order.your_order') }}"
>
    <div
        class="flex overflow-x-auto scrollbar-none gap-0 px-1"
        style="-webkit-overflow-scrolling: touch; scroll-snap-type: x proximity;"
    >
        @foreach ($categories as $cat)
            <button
                type="button"
                @click="$store.cart.scrollToCategory('{{ $cat['id'] }}')"
                :class="$store.cart.activeCategoryId === '{{ $cat['id'] }}'
                    ? 'border-b-2 text-slate-900 font-semibold'
                    : 'border-b-2 border-transparent text-slate-500 font-medium hover:text-slate-700'"
                :style="$store.cart.activeCategoryId === '{{ $cat['id'] }}'
                    ? 'border-color: var(--go-accent);'
                    : ''"
                class="shrink-0 whitespace-nowrap px-4 py-3 text-sm transition-colors duration-150 focus:outline-none"
                style="scroll-snap-align: start;"
            >
                {{ $cat['label'] }}
            </button>
        @endforeach
    </div>
</nav>
