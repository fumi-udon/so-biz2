@php
    /** @var list<array<string, mixed>> $columns */
    /** @var int $queuedBatchCount */
    /** @var bool $hasShop */
    /** @var array{offline:bool,last_fail_at:?string,last_fail_hms:?string,last_ok_hms:?string,last_fail_error:?string} $broadcastHealth */
    /** @var array<string, mixed> $kdsClientBootstrap */
@endphp
<div
    id="kds-dashboard-root"
    class="flex h-[100dvh] max-h-[100dvh] w-screen min-w-0 flex-col overflow-hidden bg-slate-950 text-slate-100"
    wire:poll.{{ max(2, min(60, (int) $pollSeconds)) }}s
    data-kds-bootstrap="@json($kdsClientBootstrap)"
    x-data="kdsEchoBridge(@js($kdsClientBootstrap))"
    x-init="$store.kdsFilters.syncFromDom($el, $data); initEcho(); $wire.updateClientFilters($store.kdsFilters.showKitchen, $store.kdsFilters.showHall); window.__kdsSeenKeys = window.__kdsSeenKeys || {};"
>
    <header class="z-10 shrink-0 border-b border-slate-800 bg-slate-900/90 backdrop-blur">
        <div class="flex min-w-0 items-center justify-between gap-1.5 px-1 py-1 sm:px-1.5">
            <div class="flex min-w-0 items-center gap-1.5 sm:gap-2">
                <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded bg-rose-600 text-xs font-bold text-white">K</span>
                <div class="min-w-0">
                    <h1 class="text-xs font-semibold leading-tight tracking-wide text-slate-100 sm:text-sm">{{ __('kds.dashboard_title') }}</h1>
                    <p class="truncate text-[10px] text-slate-500 sm:text-[11px]">{{ __('kds.dashboard_subtitle') }}</p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1 sm:gap-1.5">
                <span
                    class="inline-flex max-w-[9rem] items-center gap-1 truncate rounded border px-1.5 py-0.5 text-[10px] sm:max-w-none"
                    :class="{
                        'border-emerald-500/50 bg-emerald-900/30 text-emerald-200': status === 'connected',
                        'border-amber-500/50 bg-amber-900/30 text-amber-200': status === 'connecting',
                        'border-rose-500/50 bg-rose-900/30 text-rose-200': status === 'disconnected' || status === 'error',
                    }"
                >
                    <span class="inline-block h-1.5 w-1.5 shrink-0 rounded-full" :class="{
                        'bg-emerald-400': status === 'connected',
                        'bg-amber-400': status === 'connecting',
                        'bg-rose-400': status === 'disconnected' || status === 'error',
                    }"></span>
                    <span x-text="status" class="hidden sm:inline"></span>
                </span>
                <span class="hidden text-[10px] text-slate-500 sm:inline sm:text-xs">{{ now()->format('H:i') }}</span>
                @if ($hasShop)
                    <div class="hidden shrink-0 flex-row flex-wrap items-center gap-x-5 gap-y-2 border-l border-slate-700 pl-2 sm:flex md:gap-x-8">
                        <label class="flex min-h-12 min-w-0 cursor-pointer touch-manipulation select-none items-center gap-3 rounded-lg border border-slate-600/90 bg-slate-800/90 px-3 py-2 sm:min-h-14 sm:gap-3.5 sm:px-4 sm:py-2.5">
                            <input
                                type="checkbox"
                                class="h-5 w-5 shrink-0 cursor-pointer rounded border-slate-500 bg-slate-900 text-rose-600 sm:h-6 sm:w-6"
                                x-model="$store.kdsFilters.showKitchen"
                                @change="$wire.updateClientFilters($store.kdsFilters.showKitchen, $store.kdsFilters.showHall)"
                            />
                            <span class="whitespace-nowrap text-xs font-semibold tracking-wide text-slate-100 sm:text-sm">{{ __('kds.filter_kitchen') }}</span>
                        </label>
                        <label class="flex min-h-12 min-w-0 cursor-pointer touch-manipulation select-none items-center gap-3 rounded-lg border border-slate-600/90 bg-slate-800/90 px-3 py-2 sm:min-h-14 sm:gap-3.5 sm:px-4 sm:py-2.5">
                            <input
                                type="checkbox"
                                class="h-5 w-5 shrink-0 cursor-pointer rounded border-slate-500 bg-slate-900 text-rose-600 sm:h-6 sm:w-6"
                                x-model="$store.kdsFilters.showHall"
                                @change="$wire.updateClientFilters($store.kdsFilters.showKitchen, $store.kdsFilters.showHall)"
                            />
                            <span class="whitespace-nowrap text-xs font-semibold tracking-wide text-slate-100 sm:text-sm">{{ __('kds.filter_hall') }}</span>
                        </label>
                    </div>
                    @php
                        $q = max(0, (int) $queuedBatchCount);
                        $queueBadgeClass = $q === 0
                            ? 'bg-gray-800 text-gray-400'
                            : ($q >= 5
                                ? 'bg-amber-900/40 text-amber-100 animate-pulse'
                                : 'bg-blue-900/40 text-blue-100');
                    @endphp
                    <span
                        class="inline-flex shrink-0 items-center rounded px-2 py-0.5 text-[10px] font-semibold tabular-nums sm:text-xs {{ $queueBadgeClass }}"
                        title="{{ __('kds.queue_waiting', ['count' => $q]) }}"
                    >
                        {{ __('kds.queue_waiting', ['count' => $q]) }}
                    </span>
                    <button
                        type="button"
                        wire:click="toggleHistory"
                        wire:loading.attr="disabled"
                        wire:target="toggleHistory"
                        class="inline-flex min-h-11 shrink-0 touch-manipulation items-center justify-center rounded-lg border border-slate-600 bg-slate-700 px-2 text-[10px] font-semibold text-slate-100 active:bg-slate-600 sm:px-2.5 sm:text-xs"
                    >
                        {{ __('kds.history_button') }}
                    </button>
                @endif
                <a
                    href="{{ url('/admin') }}"
                    class="inline-flex h-11 w-11 min-h-11 min-w-11 touch-manipulation items-center justify-center rounded-md border border-slate-600 bg-slate-800 text-slate-200 hover:bg-slate-700"
                    title="{{ __('kds.kiosk_open_admin') }}"
                    aria-label="{{ __('kds.kiosk_open_admin') }}"
                >
                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.24-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.37.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.377-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <main class="mx-auto flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-y-auto px-1 py-1">
        @if ($hasShop)
            <div
                x-cloak
                x-show="$store.kdsFilters.showFilterConfigWarning"
                class="mb-1.5 shrink-0 rounded border border-amber-500/70 bg-amber-950/50 px-2 py-1.5 text-[10px] font-medium text-amber-100 sm:text-xs"
                role="status"
            >
                {{ __('kds.filter_config_warning') }}
            </div>
        @endif
        @if ($broadcastHealth['offline'])
            <div class="mb-1.5 shrink-0 rounded border border-amber-600/60 bg-amber-900/30 px-2 py-1.5 text-[10px] text-amber-100 sm:text-xs">
                <p class="font-semibold">{{ __('kds.offline_mode_title') }}</p>
                <p>
                    {{ __('kds.offline_mode_body') }}
                    @if ($broadcastHealth['last_ok_hms'] !== null)
                        · {{ __('kds.last_ok_at') }} {{ $broadcastHealth['last_ok_hms'] }}
                    @endif
                </p>
            </div>
        @endif

        @if ($broadcastHealth['last_fail_hms'] !== null)
            <div class="mb-1.5 shrink-0 rounded border border-rose-700/60 bg-rose-950/40 px-2 py-1.5 text-[10px] text-rose-100 sm:text-[11px]">
                {{ __('kds.recent_broadcast_fail') }} {{ $broadcastHealth['last_fail_hms'] }}
                @if (($broadcastHealth['last_fail_error'] ?? null) !== null)
                    · {{ $broadcastHealth['last_fail_error'] }}
                @endif
            </div>
        @endif

        @if (! $hasShop)
            <div class="rounded-lg border border-slate-700 bg-slate-900 p-6 text-center text-slate-300">
                {{ __('kds.no_shop') }}
            </div>
        @elseif (count($columns) === 0)
            <div class="rounded-lg border border-slate-800 bg-slate-900/60 p-10 text-center">
                <p class="text-base font-medium text-slate-200">{{ __('kds.empty_title') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('kds.empty_subtitle') }}</p>
            </div>
        @else
            <div class="grid min-h-0 min-w-0 flex-1 grid-cols-3 gap-1.5 pb-1 sm:gap-2">
                @foreach ($columns as $colIndex => $col)
                    @php
                        $isBacklog = in_array($col['category'] ?? '', ['staff', 'takeaway'], true);
                    @endphp
                    <section
                        wire:key="kds-batch-{{ $col['batchKey'] }}"
                        class="flex min-h-0 min-w-0 w-full flex-col rounded-lg border shadow-lg {{ $isBacklog ? 'border-slate-700 bg-slate-800/80' : 'border-slate-800 bg-slate-900/70' }}"
                    >
                        <header class="flex min-h-14 items-center justify-between border-b px-4 py-3 {{ $isBacklog ? 'border-slate-700 bg-slate-800' : 'border-slate-800 bg-slate-900' }}">
                            <h2 class="text-lg font-bold tracking-wide {{ $isBacklog ? 'text-slate-200' : 'text-slate-100' }}">
                                {{ $col['displayLabel'] ?? __('kds.table_label', ['name' => $col['tableName'] !== '' ? $col['tableName'] : '#'.$col['tableId']]) }}
                            </h2>
                            <div class="flex items-center gap-2">
                                @if ($isBacklog)
                                    <span class="rounded-full border border-slate-500 bg-slate-700 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-200">
                                        {{ $col['category'] === 'staff' ? __('kds.bucket_staff') : __('kds.bucket_takeaway') }}
                                    </span>
                                @endif
                                <span class="rounded-full bg-slate-800 px-2 py-0.5 text-xs font-semibold text-slate-300">
                                    <span x-text="$store.kdsFilters.visibleTicketCountForColumn({{ (int) $colIndex }})">{{ count($col['tickets']) }}</span>
                                </span>
                            </div>
                        </header>

                        <ul class="flex min-h-0 flex-1 flex-col gap-1.5 p-2 sm:gap-2 sm:p-2.5">
                            @foreach ($col['tickets'] as $ticket)
                                @php
                                    $isServed = $ticket->status?->value === 'served';
                                    $isCooking = $ticket->status?->value === 'cooking';
                                    $base = 'group relative flex min-h-14 items-start justify-between gap-3 rounded-lg border px-2 py-2 text-left text-base font-semibold transition select-none';
                                    $name = $ticket->snapshot_kitchen_name !== null && trim((string) $ticket->snapshot_kitchen_name) !== ''
                                        ? (string) $ticket->snapshot_kitchen_name
                                        : (string) ($ticket->snapshot_name ?? ($ticket->menuItem?->kitchen_name ?? $ticket->menuItem?->name ?? ''));
                                    $opts = is_array($ticket->snapshot_options_payload) ? $ticket->snapshot_options_payload : [];
                                    $styleName = '';
                                    if (is_array($opts['style'] ?? null)) {
                                        $rawStyle = (string) ($opts['style']['name'] ?? '');
                                        $styleName = trim($rawStyle);
                                    }
                                    $toppingNames = [];
                                    foreach ((is_array($opts['toppings'] ?? null) ? $opts['toppings'] : []) as $tp) {
                                        if (! is_array($tp)) {
                                            continue;
                                        }
                                        $tn = trim((string) ($tp['name'] ?? ''));
                                        if ($tn !== '') {
                                            $toppingNames[] = $tn;
                                        }
                                    }
                                    $qtyPrefix = ($ticket->qty ?? 1) > 1 ? '×'.$ticket->qty.' ' : '';
                                @endphp
                                <li
                                    wire:key="kds-line-{{ $ticket->id }}-r{{ $ticket->line_revision }}"
                                    x-data="{
                                        key: 'kds-line-{{ (int)$ticket->id }}-r{{ (int)$ticket->line_revision }}',
                                        isNew: {{ ($ticket->kds_is_new_arrival ?? false) ? 'true' : 'false' }},
                                        animate: false,
                                        optimistic: null,
                                        pending: false,
                                        isServedUi() { return this.optimistic !== null ? this.optimistic : {{ $isServed ? 'true' : 'false' }}; },
                                        async markServedTap(id, rev) {
                                            this.pending = true;
                                            this.optimistic = true;
                                            await $wire.markServed(id, rev);
                                            this.pending = false;
                                        },
                                        async revertTap(id, rev) {
                                            this.pending = true;
                                            this.optimistic = false;
                                            await $wire.revertToConfirmed(id, rev);
                                            this.pending = false;
                                        },
                                    }"
                                    x-show="$store.kdsFilters.ticketVisible(@js($ticket->menuItem?->menu_category_id))"
                                    @kds-wire-fail.window="if (pending) { optimistic = null; pending = false; }"
                                    x-init="
                                        window.__kdsSeenKeys = window.__kdsSeenKeys || {};
                                        if (!window.__kdsSeenKeys[key] && isNew) {
                                            animate = true;
                                        }
                                        window.__kdsSeenKeys[key] = true;
                                    "
                                    x-transition:enter="ease-out duration-300"
                                    x-transition:enter-start="-translate-y-2 opacity-0"
                                    x-transition:enter-end="translate-y-0 opacity-100"
                                    :class="animate ? 'ring-2 ring-amber-300/90 ring-offset-1 ring-offset-slate-900' : ''"
                                >
                                    <button
                                        type="button"
                                        x-show="isServedUi()"
                                        class="{{ $base }} w-full cursor-pointer"
                                        :class="isServedUi() ? 'border-slate-600 bg-slate-700/70 text-slate-200 hover:bg-slate-700/80' : 'border-rose-700 bg-rose-900/50 text-rose-50 hover:bg-rose-900/70'"
                                        @click="revertTap({{ (int)$ticket->id }}, {{ (int)$ticket->line_revision }})"
                                        title="{{ __('kds.revert_hint') }}"
                                    >
                                        <span class="flex min-w-0 flex-col gap-1">
                                            <span class="text-lg leading-tight text-slate-200">
                                                {{ $qtyPrefix }}{{ $name }}@if ($styleName !== '') <span class="font-bold">[{{ $styleName }}]</span>@endif
                                            </span>
                                            @if (! empty($toppingNames))
                                                <span class="text-xs font-normal leading-snug text-slate-400 line-through decoration-2">+ {{ implode(', ', $toppingNames) }}</span>
                                            @endif
                                        </span>
                                        <span class="shrink-0 rounded-full bg-emerald-700/60 px-2 py-1 text-xs text-emerald-50">
                                            ✓
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        x-show="!isServedUi()"
                                        class="{{ $base }} w-full cursor-pointer"
                                        :class="isServedUi() ? 'border-slate-600 bg-slate-700/70 text-slate-200 hover:bg-slate-700/80' : 'border-rose-700 bg-rose-900/50 text-rose-50 hover:bg-rose-900/70'"
                                        @click="markServedTap({{ (int)$ticket->id }}, {{ (int)$ticket->line_revision }})"
                                    >
                                        <span class="flex min-w-0 flex-col gap-1">
                                            @if ($isCooking)
                                                <span class="text-xs font-medium uppercase tracking-wider text-rose-200/90">
                                                    {{ __('kds.status_cooking') }}
                                                </span>
                                            @endif
                                            <span class="text-lg leading-tight text-rose-50">
                                                {{ $qtyPrefix }}{{ $name }}@if ($styleName !== '') <span class="font-bold">[{{ $styleName }}]</span>@endif
                                            </span>
                                            @if (! empty($toppingNames))
                                                <span class="text-xs font-normal leading-snug text-rose-200/90">+ {{ implode(', ', $toppingNames) }}</span>
                                            @endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </main>

    @if ($hasShop)
        <div x-data="{ open: @entangle('historyOpen').live }">
            <div x-show="open" x-cloak class="fixed inset-0 z-50 flex">
                <button
                    type="button"
                    class="min-h-0 flex-1 cursor-default bg-slate-950/60"
                    aria-label="{{ __('kds.history_close') }}"
                    @click="$wire.set('historyOpen', false)"
                ></button>
                <aside
                    x-transition:enter="transform transition duration-300 ease-out"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transform transition duration-300 ease-in"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="flex h-full w-full max-w-md flex-col border-l border-slate-700 bg-slate-900 text-slate-100 shadow-2xl sm:w-md"
                >
                    <div class="flex min-h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-700 px-4 py-3">
                        <h2 class="text-lg font-bold text-slate-100">{{ __('kds.history_title') }}</h2>
                        <button
                            type="button"
                            class="min-h-14 min-w-14 rounded-lg border border-slate-600 bg-slate-800 px-4 text-slate-100"
                            wire:click="toggleHistory"
                            wire:loading.attr="disabled"
                            wire:target="toggleHistory"
                        >
                            ✕
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-y-auto p-4">
                        @forelse ($this->historyColumns as $session)
                            <section
                                wire:key="kds-history-session-{{ $session['sessionId'] }}"
                                @class([
                                    'mt-3 rounded-xl border p-3 first:mt-0',
                                    'border-slate-600 bg-slate-800 text-slate-100' => ! ($session['isClosed'] ?? false),
                                    'border-slate-700 bg-slate-900/50 text-slate-300' => ($session['isClosed'] ?? false),
                                ])
                            >
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-semibold">
                                        {{ __('kds.table_label', ['name' => ($session['tableName'] ?? '') !== '' ? $session['tableName'] : '#'.$session['sessionId']]) }}
                                    </h3>
                                    @if ($session['isClosed'] ?? false)
                                        <span class="rounded-full border border-slate-600 bg-slate-800 px-2 py-0.5 text-xs font-medium text-slate-400">
                                            {{ __('kds.history_closed_badge') }}
                                        </span>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ optional($session['openedAt'])->format('Y-m-d H:i') }}
                                </p>
                                <ul class="mt-3 flex flex-col gap-2">
                                    @foreach ($session['lines'] as $hLine)
                                        @php
                                            $ticket = $hLine;
                                            $isServed = $ticket->status?->value === 'served';
                                            $base = 'flex min-h-14 flex-col gap-2 rounded-lg border px-4 py-3 text-left text-sm font-medium';
                                            $sessionMuted = ($session['isClosed'] ?? false);
                                            $rowBg = $sessionMuted
                                                ? 'border-slate-600 bg-slate-800/60 text-slate-300'
                                                : 'border-slate-600 bg-slate-800 text-slate-100';
                                            $name = $ticket->snapshot_kitchen_name !== null && trim((string) $ticket->snapshot_kitchen_name) !== ''
                                                ? (string) $ticket->snapshot_kitchen_name
                                                : (string) ($ticket->snapshot_name ?? ($ticket->menuItem?->kitchen_name ?? $ticket->menuItem?->name ?? ''));
                                            $opts = is_array($ticket->snapshot_options_payload) ? $ticket->snapshot_options_payload : [];
                                            $styleName = '';
                                            if (is_array($opts['style'] ?? null)) {
                                                $rawStyle = (string) ($opts['style']['name'] ?? '');
                                                $styleName = trim($rawStyle);
                                            }
                                            $toppingNames = [];
                                            foreach ((is_array($opts['toppings'] ?? null) ? $opts['toppings'] : []) as $tp) {
                                                if (! is_array($tp)) {
                                                    continue;
                                                }
                                                $tn = trim((string) ($tp['name'] ?? ''));
                                                if ($tn !== '') {
                                                    $toppingNames[] = $tn;
                                                }
                                            }
                                            $qtyPrefix = ($ticket->qty ?? 1) > 1 ? '×'.$ticket->qty.' ' : '';
                                            $titleColor = $sessionMuted ? 'text-slate-200' : 'text-slate-100';
                                            $modColor = $sessionMuted ? 'text-slate-400' : 'text-slate-300';
                                        @endphp
                                        <li wire:key="kds-history-line-{{ $ticket->id }}-r{{ $ticket->line_revision }}">
                                            <div class="{{ $base }} {{ $rowBg }}">
                                                <div class="flex min-w-0 flex-col gap-1">
                                                    <span class="text-base leading-tight {{ $titleColor }}">
                                                        {{ $qtyPrefix }}{{ $name }}@if ($styleName !== '') <span class="font-bold">[{{ $styleName }}]</span>@endif
                                                    </span>
                                                    @if (! empty($toppingNames))
                                                        <span class="text-xs font-normal leading-snug {{ $modColor }}">+ {{ implode(', ', $toppingNames) }}</span>
                                                    @endif
                                                </div>
                                                @if ($isServed)
                                                    <button
                                                        type="button"
                                                        class="min-h-14 w-full rounded-lg border border-rose-600 bg-rose-800 px-4 py-3 text-center text-base font-semibold text-rose-50 active:bg-rose-700"
                                                        wire:click="revertToConfirmed({{ (int) $ticket->id }}, {{ (int) $ticket->line_revision }})"
                                                        wire:loading.attr="disabled"
                                                        wire:target="revertToConfirmed"
                                                    >
                                                        {{ __('kds.history_revert') }}
                                                    </button>
                                                @endif
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @empty
                            <p class="mt-12 text-center text-sm text-slate-400">{{ __('kds.history_empty') }}</p>
                        @endforelse
                    </div>
                </aside>
            </div>
        </div>
    @endif
</div>
