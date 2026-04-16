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
    class="pt-5 pb-2"
    style="scroll-margin-top: 56px;"
>
    <div class="px-4 mb-3">
        <span
            class="inline-flex items-center px-4 py-1.5 rounded-full border text-xs font-semibold tracking-wide text-slate-700 bg-white border-slate-300"
        >
            {{ $categoryLabel }}
        </span>
    </div>

    <div class="flex flex-col gap-3 px-3">
        {{ $slot }}
    </div>
</section>
