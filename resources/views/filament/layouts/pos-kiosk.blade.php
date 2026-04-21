@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div
        class="fi-pos-kiosk-shell flex h-[100dvh] max-h-[100dvh] w-screen max-w-[100vw] flex-col overflow-hidden overscroll-none bg-gray-50 dark:bg-gray-950"
    >
        <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
            {{ $slot }}
        </div>
    </div>
</x-filament-panels::layout.base>
