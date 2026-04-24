{{--
    Category sticky tab rail.
    Props:
      $categories  – array of ['id' => string, 'label' => string]
--}}
@props(['categories'])

<nav
    class="sticky top-0 z-30 max-w-full border-b border-slate-200 bg-white/95 text-gray-950 backdrop-blur-sm dark:border-slate-700 dark:bg-gray-900/95 dark:text-white md:top-0 md:z-20 md:w-52 md:max-w-full md:shrink-0 md:self-start md:border-b-0 md:border-r md:overflow-hidden"
    aria-label="{{ __('guest-order.your_order') }}"
>
    <div
        class="[scrollbar-width:none] [&::-webkit-scrollbar]:hidden scrollbar-none flex gap-0 overflow-x-auto px-1 md:max-h-[calc(100dvh-3.5rem)] md:flex-col md:gap-0.5 md:overflow-y-auto md:overflow-x-hidden md:px-2 md:py-2"
        style="-webkit-overflow-scrolling: touch; scroll-snap-type: x proximity;"
    >
        @foreach ($categories as $cat)
            <button
                type="button"
                @click="$store.cart.scrollToCategory('{{ $cat['id'] }}')"
                :class="$store.cart.activeCategoryId === '{{ $cat['id'] }}'
                    ? 'border-b-2 font-semibold text-slate-900 md:border-b-0 md:border-l-2 dark:text-white'
                    : 'border-b-2 border-transparent text-slate-500 font-medium hover:text-slate-700 md:border-b-0 md:border-l-2 md:border-transparent dark:text-slate-400 dark:hover:text-slate-200'"
                :style="$store.cart.activeCategoryId === '{{ $cat['id'] }}'
                    ? 'border-color: var(--go-accent);'
                    : ''"
                class="shrink-0 snap-start whitespace-nowrap px-4 py-3 text-sm transition-colors duration-150 focus:outline-none md:w-full md:whitespace-normal md:rounded-lg md:px-3 md:py-2.5 md:text-left"
                style="scroll-snap-align: start;"
            >
                {{ $cat['label'] }}
            </button>
        @endforeach
    </div>
</nav>
