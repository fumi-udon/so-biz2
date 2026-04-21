@props([
    'tile' => [],
])

@php
    $color = $tile['color'] ?? 'white';
    $wrap = match ($color) {
        'red' => 'border-slate-200 bg-red-50 border-l-4 border-l-red-500 text-gray-950 dark:border-slate-600 dark:bg-red-950/40 dark:text-white dark:border-l-red-500',
        'green' => 'border-slate-200 bg-emerald-50 border-l-4 border-l-emerald-500 text-gray-950 dark:border-slate-600 dark:bg-emerald-950/40 dark:text-white dark:border-l-emerald-500',
        'yellow' => 'border-slate-200 bg-amber-50 border-l-4 border-l-amber-400 text-gray-950 dark:border-slate-600 dark:bg-amber-950/40 dark:text-amber-100 dark:border-l-amber-400',
        default => 'border-slate-200 bg-slate-100 border-l-4 border-l-slate-400 text-gray-950 dark:border-slate-600 dark:bg-slate-900 dark:text-white dark:border-l-slate-500',
    };
    $badge = match ($color) {
        'red' => 'bg-red-600 text-white',
        'green' => 'bg-emerald-600 text-white',
        'yellow' => 'bg-amber-500 text-amber-950 dark:bg-amber-400 dark:text-amber-950',
        default => 'bg-slate-600 text-white dark:bg-slate-500 dark:text-white',
    };
    $sessionId = $tile['session_id'] ?? null;
@endphp

<x-filament::section
    :compact="true"
    :heading="(string) ($tile['table_name'] ?? '')"
    class="rounded-2xl shadow-sm {{ $wrap }}"
>
    <x-slot name="headerEnd">
        <x-filament::badge class="{{ $badge }}">
            {{ $tile['badge_label'] ?? '' }}
        </x-filament::badge>
    </x-slot>

    <div class="space-y-1">
        <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">
            {{ $tile['total_label'] ?? '0.000 TND' }}
        </p>
        <p class="text-sm text-gray-800 dark:text-gray-200">
            {{ $tile['dwell_label'] ?? '—' }}
        </p>
    </div>

    @if ($sessionId)
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <x-filament::button
                size="lg"
                color="success"
                class="min-h-[48px] w-full justify-center"
                wire:click="recu({{ (int) $sessionId }})"
                wire:loading.attr="disabled"
                wire:target="recu"
                :disabled="! ($tile['can_recu'] ?? false)"
            >
                <span wire:loading.remove wire:target="recu">{{ __('rad_table.recu') }}</span>
                <span wire:loading wire:target="recu">…</span>
            </x-filament::button>

            <x-filament::button
                size="lg"
                color="warning"
                class="min-h-[48px] w-full justify-center"
                wire:click="addition({{ (int) $sessionId }})"
                wire:loading.attr="disabled"
                wire:target="addition"
                :disabled="! ($tile['can_addition'] ?? false)"
            >
                <span wire:loading.remove wire:target="addition">{{ __('rad_table.addition') }}</span>
                <span wire:loading wire:target="addition">…</span>
            </x-filament::button>

            <x-filament::button
                size="lg"
                color="danger"
                outlined
                class="min-h-[48px] w-full justify-center"
                wire:click="checkout({{ (int) $sessionId }})"
                wire:confirm="{{ __('rad_table.checkout_confirm') }}"
                wire:loading.attr="disabled"
                wire:target="checkout"
                :disabled="! ($tile['can_checkout'] ?? false)"
            >
                <span wire:loading.remove wire:target="checkout">{{ __('rad_table.checkout') }}</span>
                <span wire:loading wire:target="checkout">…</span>
            </x-filament::button>
        </div>
    @endif
</x-filament::section>
