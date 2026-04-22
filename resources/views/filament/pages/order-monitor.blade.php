<x-filament-panels::page>
    <div class="space-y-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('Les nouvelles commandes sont synchronisees en temps reel via Pusher (tables 1 a 4).') }}
        </p>

        <div class="grid gap-4 sm:grid-cols-2">
            @foreach (config('restaurant.tables', ['1', '2', '3', '4']) as $table)
                @php
                    $blink = $blinkingTable === $table;
                    $orders = $ordersByTable[$table] ?? [];
                @endphp
                <div
                    wire:key="table-{{ $table }}"
                    @class([
                        'rounded-xl border p-4 transition-shadow duration-300',
                        'border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900' => ! $blink,
                        'border-red-500 bg-red-50 ring-2 ring-red-400 dark:border-red-500 dark:bg-red-950/40 dark:ring-red-500' => $blink,
                        'animate-pulse' => $blink,
                    ])
                >
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        {{ __('Table') }} {{ $table }}
                    </h2>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ __('En attente') }}: {{ count($orders) }}
                    </p>

                    <ul class="mt-3 space-y-2">
                        @forelse ($orders as $row)
                            <li
                                class="rounded-lg border border-gray-100 bg-gray-50/80 p-3 text-sm text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100"
                            >
                                <span class="font-mono text-xs text-gray-500">#{{ $row['id'] }}</span>
                                @if (! empty($row['created_at']))
                                    <span class="ml-2 text-xs text-gray-500">{{ $row['created_at'] }}</span>
                                @endif
                                <p class="mt-1 whitespace-pre-wrap">{{ $row['items'] }}</p>
                            </li>
                        @empty
                            <li class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Aucune commande') }}
                            </li>
                        @endforelse
                    </ul>
                </div>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
