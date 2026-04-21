@props([
    'title',
    'subtitle' => '',
    'note' => '',
])

<div class="w-full max-w-md rounded-2xl border-4 border-black bg-white p-4 text-gray-900 shadow-[0_12px_0_0_rgba(0,0,0,1)] dark:bg-gray-950 dark:text-gray-100">
    <h2 class="mb-1 text-xl font-black tracking-wider text-gray-900 dark:text-white">{{ $title }}</h2>
    @if ($subtitle !== '')
        <p class="mb-3 text-sm font-semibold text-gray-600 dark:text-gray-300">{{ $subtitle }}</p>
    @endif
    @if ($note !== '')
        <p class="-mt-2 mb-3 text-[10px] text-gray-400 dark:text-gray-500">{{ $note }}</p>
    @endif

    <div class="space-y-2">
        {{ $slot }}
    </div>
</div>

