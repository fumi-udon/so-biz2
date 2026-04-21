@php
    /** @var list<array<string, mixed>> $tiles */
    /** @var string $shopName */
@endphp

<x-filament-panels::page>
    <div class="space-y-4">
        @if ($shopName !== '')
            <p class="text-sm text-gray-700 dark:text-gray-200">
                <span class="font-medium text-gray-950 dark:text-white">{{ __('rad_table.shop_label') }}</span>
                <span class="ms-1">{{ $shopName }}</span>
            </p>
        @else
            <x-filament::section>
                <p class="text-sm text-gray-700 dark:text-gray-200">{{ __('rad_table.empty_shop') }}</p>
            </x-filament::section>
        @endif

        <div
            wire:poll.10s
            class="grid grid-cols-2 gap-3 md:grid-cols-4"
        >
            @foreach ($tiles as $tile)
                <div wire:key="rad-tile-{{ $tile['table_id'] }}">
                    <x-rad-table-tile :tile="$tile" />
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
