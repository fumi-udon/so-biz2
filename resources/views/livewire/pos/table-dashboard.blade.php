@if (config('app.speed_test', env('SPEED_TEST', false)))
    <div class="pointer-events-none fixed inset-x-2 bottom-2 z-[460]">
        <details class="pointer-events-auto mx-auto w-full max-w-5xl overflow-hidden rounded-lg border border-zinc-700/90 bg-zinc-950/95 text-zinc-100 shadow-2xl" open>
            <summary class="cursor-pointer list-none border-b border-zinc-700/90 px-3 py-2 text-xs font-bold tracking-wide text-amber-300 [&::-webkit-details-marker]:hidden">
                SPEED TEST DEBUG PANEL
            </summary>
            <div class="max-h-[42dvh] overflow-y-auto overscroll-contain">
                <x-pos-speed-panel />
                <x-livewire-payload-monitor />
                <x-pos-speed-probe-panel />
            </div>
        </details>
    </div>
@endif
