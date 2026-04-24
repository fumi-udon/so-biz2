{{--
    Category section anchor + header pill.
    Props:
      $categoryId    – string  e.g. 'ramen'
      $categoryLabel – string  e.g. 'RAMEN'
    Default slot: product cards
--}}
@props(['categoryId', 'categoryLabel'])

<section
    id="cat-{{ $categoryId }}"
    class="min-w-0 max-w-full pt-5 pb-2"
    style="scroll-margin-top: 56px;"
>
    <div class="mb-3 max-w-full px-4">
        <span
            class="inline-flex max-w-full items-center rounded-full border border-slate-300 bg-white px-4 py-1.5 text-xs font-semibold tracking-wide text-gray-950 dark:border-slate-600 dark:bg-gray-900 dark:text-white"
        >
            {{ $categoryLabel }}
        </span>
    </div>

    <div class="flex max-w-full min-w-0 flex-col gap-3 px-3 sm:grid sm:grid-cols-2 md:grid-cols-3">
        {{ $slot }}
    </div>
</section>
