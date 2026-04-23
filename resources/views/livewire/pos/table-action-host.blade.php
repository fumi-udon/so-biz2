@php
    $open = $this->activeRestaurantTableId !== null;
    $footerLocked = $this->footerActionsLocked;
    $echoShopId = (int) $this->shopId;
    $zOverlayBackdrop = 'z-[300]';
    $zOverlayPanel = 'z-[310]';
    $zStaffMealBackdrop = 'z-[315]';
    $zStaffMealPanel = 'z-[325]';
    $zAddModal = 'z-[260]';
    $zAddModalPanel = 'z-[270]';
    $legacyOrderPlacedChannel = 'pos.shop.'.$echoShopId;
    $rtOrderChannel = 'rt.shop.'.$echoShopId.'.orders';
@endphp

<div
    class="fi-no-print flex h-full min-h-0 w-full min-w-0 flex-col border-s-4 border-blue-600 bg-linear-to-b from-white via-blue-50 to-blue-100 text-slate-900 dark:border-blue-500 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950"
    x-data="{
        isLocalSkeletonVisible: false,
        localSkeletonToken: null,
        seenUnsentLineKeys: {},
        closeDrawer() {
            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('pos-tile-interaction-ended');
            }
            this.isLocalSkeletonVisible = false;
            this.localSkeletonToken = null;
            $wire.closeHost();
        },
        clearPaneSkeletonAfterMorph() {
            const token = this.localSkeletonToken;
            requestAnimationFrame(() => {
                requestAnimationFrame(() => {
                    if (this.localSkeletonToken === token) {
                        this.isLocalSkeletonVisible = false;
                    }
                });
            });
        },
        shouldAnimateUnsent(key, isFresh) {
            if (!isFresh) {
                this.seenUnsentLineKeys[key] = true;
                return false;
            }
            if (this.seenUnsentLineKeys[key]) {
                return false;
            }
            this.seenUnsentLineKeys[key] = true;
            return true;
        },
    }"
    x-init="
        (function () {
            const shopId = {{ $echoShopId }};
            const bind = function () {
                if (! window.Echo || ! window.Livewire) {
                    return;
                }
                // F5手動更新運用への移行・および通信量削減のためPusherリスナーを無効化
                /*
                window.__posOrderPlacedEcho = window.__posOrderPlacedEcho || {};
                if (window.__posOrderPlacedEcho[shopId]) {
                    return;
                }
                window.__posOrderPlacedEcho[shopId] = true;
                window.Echo.private(@js($legacyOrderPlacedChannel)).listen('.pos.order.placed', function (payload) {
                    window.Livewire.dispatch('pos-echo-order-placed', {
                        shop_id: payload.shop_id,
                        table_session_id: payload.table_session_id,
                    });
                });
                window.Echo.private(@js($rtOrderChannel)).listen('.pos.order.placed', function (payload) {
                    window.Livewire.dispatch('pos-echo-order-placed', {
                        shop_id: payload.shop_id,
                        table_session_id: payload.table_session_id,
                    });
                });
                */
            };
            bind();
            window.addEventListener('EchoLoaded', bind);
        })();
    "
    x-on:show-local-skeleton.window="
        const detail = $event.detail || {}
        localSkeletonToken = detail.token ?? Date.now()
        isLocalSkeletonVisible = true
    "
    x-on:pos-tile-interaction-started.window="
        if (!isLocalSkeletonVisible) {
            localSkeletonToken = Date.now()
            isLocalSkeletonVisible = true
        }
    "
    x-on:pos-action-host-opened.window="
        clearPaneSkeletonAfterMorph()
    "
    x-on:pos-tile-interaction-ended.window="
        isLocalSkeletonVisible = false
        localSkeletonToken = null
    "
>
    @if (! $open)
        <div
            wire:key="pane-welcome"
            x-cloak
            x-show="!isLocalSkeletonVisible && !@js($open)"
            class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                {{ __('pos.detail_pick_table') }}
            </p>
        </div>
    @else
        <div
            wire:key="pane-local-skeleton"
            x-cloak
            x-show="isLocalSkeletonVisible"
            class="flex min-h-0 flex-1 flex-col"
        >
            <div class="flex shrink-0 items-center justify-between gap-1 border-b-4 border-blue-600 bg-white px-1.5 py-1 dark:border-blue-500 dark:bg-slate-900">
                <div class="min-w-0 w-full">
                    <div class="h-4 w-40 animate-pulse rounded bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <div class="mt-1 h-3 w-28 animate-pulse rounded bg-slate-200/90 dark:bg-slate-700/70"></div>
                </div>
            </div>
            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-1 py-[2px]">
                <div class="space-y-1 py-1">
                    <div class="h-6 w-full animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <div class="h-6 w-11/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <div class="h-6 w-10/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                </div>
            </div>
        </div>

        <div wire:key="pane-real-content" x-cloak x-show="!isLocalSkeletonVisible" class="min-h-0 flex flex-1 flex-col">
        {{-- Header: table name + primary actions --}}
        <div
            class="flex shrink-0 items-center justify-between gap-1 border-b-4 border-blue-600 bg-white px-1.5 py-1 dark:border-blue-500 dark:bg-slate-900"
        >
            <div class="min-w-0">
                <h2 class="line-clamp-1 text-sm font-black text-gray-950 dark:text-white">
                    {{ $this->activeSessionLabel }}
                </h2>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <span class="rounded-full border-2 border-blue-600 bg-blue-100 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-blue-900 dark:border-blue-500 dark:bg-blue-950/50 dark:text-blue-100">
                        {{ $this->activeCategoryLabel }}
                    </span>
                    @if ($this->isBilledState)
                        <span class="rounded-full border-2 border-amber-500 bg-amber-200 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-amber-900 dark:border-amber-400 dark:bg-amber-900/50 dark:text-amber-100">
                            {{ __('rad_table.badge_printed') }}
                        </span>
                    @endif
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <button
                    type="button"
                    wire:click="ajouter"
                    data-pos-ajouter-primary
                    @disabled($footerLocked)
                    wire:loading.attr="disabled"
                    wire:target="ajouter"
                    class="rounded-md border-2 border-sky-950 bg-sky-400 px-1.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-gray-950 shadow-md hover:bg-sky-300 dark:text-gray-950 focus:ring-2 focus:ring-sky-200 disabled:cursor-not-allowed disabled:opacity-50 sm:px-2 sm:py-1.5 sm:text-[11px]"
                >
                    {{ __('pos.action_ajouter') }}
                </button>
                <button
                    type="button"
                    wire:click="confirmOrders"
                    @disabled(($this->activeTableSessionId === null || $this->session === null) || $footerLocked)
                    wire:loading.attr="disabled"
                    wire:target="confirmOrders"
                    class="rounded-md border-2 border-blue-950 bg-blue-500 px-1.5 py-1 text-[10px] font-extrabold uppercase tracking-wide text-white shadow-md hover:bg-blue-600 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 sm:px-2 sm:py-1.5 sm:text-[11px]"
                >
                    {{ __('pos.action_recu_staff') }}
                </button>
            </div>
        </div>

        <div
            class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-1 py-[2px]"
        >
            @if (! $this->isOrdersLoaded)
                <div class="space-y-1 py-1">
                    <div class="h-6 w-full animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <div class="h-6 w-11/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <div class="h-6 w-10/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                    <p class="pt-1 text-[11px] text-slate-600 dark:text-slate-300">
                        {{ __('pos.ui_working') }}
                    </p>
                </div>
            @elseif ($this->activeTableSessionId === null)
                <p class="text-sm text-gray-800 dark:text-gray-100">
                    {{ __('pos.drawer_no_session') }}
                </p>
            @elseif ($this->posOrders->isEmpty())
                <p class="text-sm text-gray-800 dark:text-gray-100">
                    {{ __('pos.drawer_no_orders') }}
                </p>
            @else
                <div class="space-y-0.5">
                    <section>
                        @if ($this->unsentLines->isNotEmpty())
                            <ul class="flex flex-col gap-0.5">
                                @foreach ($this->unsentLines as $line)
                                    @php
                                        $opt = $this->lineExtraLineForTable(
                                            is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : null
                                        );
                                        $lineKey = 'pos-unsent-'.(int) $line->id.'-r'.(int) $line->line_revision;
                                        $isFreshUnsent = $this->isFreshUnsentLine($line);
                                    @endphp
                                    <li
                                        wire:key="ol-unsent-{{ (int) $line->id }}-r{{ (int) $line->line_revision }}"
                                        class="grid grid-cols-[auto_1fr_auto] items-start gap-x-1 gap-y-0 rounded-md border border-rose-200 bg-rose-50 px-1.5 py-[2px] shadow-sm dark:border-rose-800 dark:bg-rose-950/20"
                                        x-data="{ show: false, pulse: false }"
                                        x-init="
                                            const shouldAnimate = shouldAnimateUnsent('{{ $lineKey }}', {{ $isFreshUnsent ? 'true' : 'false' }});
                                            pulse = shouldAnimate;
                                            show = true;
                                            if (shouldAnimate) { setTimeout(() => pulse = false, 2600); }
                                        "
                                        x-show="show"
                                        x-transition:enter="ease-out duration-250"
                                        x-transition:enter-start="-translate-y-2 opacity-0"
                                        x-transition:enter-end="translate-y-0 opacity-100"
                                        :class="pulse ? 'ring-2 ring-amber-400 ring-offset-1 ring-offset-white dark:ring-offset-slate-900' : ''"
                                    >
                                        <button
                                            type="button"
                                            wire:click="promptRemoveLine({{ (int) $line->id }})"
                                            @if (! $this->isBilledState)
                                                wire:confirm="{{ __('pos.remove_line_confirm') }}"
                                            @endif
                                            @disabled($footerLocked)
                                            wire:loading.attr="disabled"
                                            wire:target="promptRemoveLine({{ (int) $line->id }})"
                                            class="row-span-1 flex h-5 w-5 shrink-0 items-center justify-center self-start rounded border border-slate-400 bg-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-300 focus:ring-1 focus:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                                            title="{{ __('pos.remove_line') }}"
                                        >
                                            ×
                                        </button>
                                        <div class="min-w-0 self-center text-[12px] font-extrabold leading-tight text-gray-950 dark:text-white sm:text-[13px]">
                                            <span class="inline-flex min-w-0 items-baseline gap-1">
                                                <span class="shrink-0 tabular-nums">{{ (int) $line->qty }}</span>
                                                <span class="min-w-0 truncate">{{ $this->linePrimaryText($line) }}</span>
                                            </span>
                                        </div>
                                        <p class="shrink-0 self-center text-[12px] font-extrabold tabular-nums text-gray-950 dark:text-white sm:text-[13px]">
                                            {{ $this->formatMinor((int) $line->line_total_minor) }}
                                        </p>
                                        @if ($opt !== '')
                                            <p class="col-span-3 min-w-0 pl-6 text-[10px] leading-tight text-gray-700 dark:text-gray-200 sm:pl-8 sm:text-[11px] sm:leading-snug">
                                                {{ $opt }}
                                            </p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </section>

                    <section>
                        <ul class="flex flex-col gap-0.5">
                            @foreach ($this->sentLines as $line)
                                @php
                                    $opt = $this->lineExtraLineForTable(
                                        is_array($line->snapshot_options_payload) ? $line->snapshot_options_payload : null
                                    );
                                @endphp
                                <li
                                    class="grid grid-cols-[auto_1fr_auto] items-start gap-x-1 gap-y-0 rounded-md border border-slate-200 bg-slate-100 px-1.5 py-[2px] opacity-90 shadow-sm dark:border-slate-700 dark:bg-slate-900/60"
                                    wire:key="ol-sent-{{ (int) $line->id }}-r{{ (int) $line->line_revision }}"
                                >
                                    <button
                                        type="button"
                                        wire:click="promptRemoveLine({{ (int) $line->id }})"
                                        @if (! $this->isBilledState)
                                            wire:confirm="{{ __('pos.remove_line_confirm') }}"
                                        @endif
                                        @disabled($footerLocked)
                                        wire:loading.attr="disabled"
                                        wire:target="promptRemoveLine({{ (int) $line->id }})"
                                        class="row-span-1 flex h-5 w-5 shrink-0 items-center justify-center self-start rounded border border-slate-400 bg-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-300 focus:ring-1 focus:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                                        title="{{ __('pos.remove_line') }}"
                                    >
                                        ×
                                    </button>
                                    <div class="min-w-0 self-center text-[12px] font-medium leading-tight text-slate-700 dark:text-slate-200 sm:text-[13px]">
                                        <span class="inline-flex min-w-0 items-baseline gap-1">
                                            <span class="shrink-0 tabular-nums">{{ (int) $line->qty }}</span>
                                            <span class="min-w-0 truncate">{{ $this->linePrimaryText($line) }}</span>
                                        </span>
                                    </div>
                                    <p class="shrink-0 self-center text-[12px] font-semibold tabular-nums text-slate-700 dark:text-slate-200 sm:text-[13px]">
                                        {{ $this->formatMinor((int) $line->line_total_minor) }}
                                    </p>
                                    @if ($opt !== '')
                                        <p class="col-span-3 min-w-0 pl-6 text-[10px] leading-tight text-slate-500 dark:text-slate-400 sm:pl-8 sm:text-[11px] sm:leading-snug">
                                            {{ $opt }}
                                        </p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </section>
                </div>
            @endif
        </div>
        </div>

    @if ($open && $this->requiresStaffMealAuth && ! $this->staffMealAuthModalDismissed)
        <div
            class="fixed inset-0 {{ $zStaffMealBackdrop }} flex items-center justify-center bg-black/75 p-4"
            style="isolation: isolate"
            role="dialog"
            aria-modal="true"
            wire:key="pos-staff-meal-auth-{{ (int) ($this->activeRestaurantTableId ?? 0) }}-{{ (int) ($this->activeTableSessionId ?? 0) }}"
        >
            <div class="absolute inset-0" wire:click="cancelStaffMealAuth"></div>
            <div class="relative {{ $zStaffMealPanel }} w-full max-w-md">
                <x-staff-pin-auth-card
                    :title="__('pos.staff_meal_auth_title')"
                    :subtitle="__('pos.staff_meal_auth_subtitle')"
                    :note="__('pos.staff_meal_auth_note')"
                >
                    <div>
                        <label class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200" for="staff-meal-auth-staff">{{ __('pos.staff_meal_auth_staff_label') }}</label>
                        <div class="relative">
                            <select
                                id="staff-meal-auth-staff"
                                wire:model.blur="staffMealAuthStaffId"
                                wire:loading.attr="disabled"
                                wire:target="confirmStaffMealAuth"
                                class="block w-full appearance-none rounded-lg border-2 border-black bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="">{{ __('pos.staff_meal_auth_staff_placeholder') }}</option>
                                @foreach ($this->staffMealAuthOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-700 dark:text-gray-300">▾</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200" for="staff-meal-auth-pin">{{ __('pos.staff_meal_auth_pin_label') }}</label>
                        <input
                            id="staff-meal-auth-pin"
                            type="text"
                            name="pos_staff_meal_pin"
                            inputmode="numeric"
                            maxlength="4"
                            autocomplete="off"
                            autocorrect="off"
                            spellcheck="false"
                            style="-webkit-text-security: disc"
                            wire:model="staffMealAuthPin"
                            wire:loading.attr="disabled"
                            wire:target="confirmStaffMealAuth"
                            class="block w-full rounded-lg border-2 border-black bg-white px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.3em] text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="••••"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="cancelStaffMealAuth"
                            wire:loading.attr="disabled"
                            wire:target="cancelStaffMealAuth,confirmStaffMealAuth"
                            class="rounded-lg border-2 border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        >
                            {{ __('pos.staff_meal_auth_leave') }}
                        </button>
                        <button
                            type="button"
                            wire:click="confirmStaffMealAuth"
                            wire:loading.attr="disabled"
                            wire:target="confirmStaffMealAuth"
                            class="rounded-lg border-2 border-black bg-emerald-400 px-3 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none"
                        >
                            {{ __('pos.staff_meal_auth_confirm') }}
                        </button>
                    </div>
                </x-staff-pin-auth-card>
            </div>
        </div>
    @endif

    @if ($this->removeAuthPanelOpen)
        <div class="fixed inset-0 {{ $zOverlayBackdrop }} flex items-center justify-center bg-black/70 p-4" style="isolation: isolate" role="dialog" aria-modal="true" wire:key="pos-remove-auth-modal">
            <div class="absolute inset-0" wire:click="cancelRemoveWithAuth"></div>
            <div class="relative {{ $zOverlayPanel }}">
                <x-staff-pin-auth-card
                    :title="__('pos.remove_line_auth_required_title')"
                    :subtitle="__('pos.remove_line_auth_required_body')"
                    note="本人確認（4桁PIN）"
                >
                    <div>
                        <label class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200">{{ __('pos.discount_approver') }}</label>
                        <div class="relative">
                            <select
                                wire:model="removeApproverStaffId"
                                wire:loading.attr="disabled"
                                wire:target="confirmRemoveWithAuth"
                                class="block w-full appearance-none rounded-lg border-2 border-black bg-white px-3 py-2.5 pr-10 text-sm font-semibold text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                            >
                                <option value="">Veuillez selectionner</option>
                                @if (count($this->removeApproverOptions) === 0)
                                    <option value="" disabled>Aucun personnel actif</option>
                                @endif
                                @foreach ($this->removeApproverOptions as $opt)
                                    <option value="{{ $opt['id'] }}">{{ $opt['name'] }} (Lv{{ $opt['level'] }})</option>
                                @endforeach
                            </select>
                            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-gray-700 dark:text-gray-300">▾</span>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-black tracking-wide text-gray-800 dark:text-gray-200">{{ __('pos.discount_pin') }} (4 chiffres)</label>
                        <input
                            type="password"
                            inputmode="numeric"
                            wire:model="removeApproverPin"
                            wire:loading.attr="disabled"
                            wire:target="confirmRemoveWithAuth"
                            class="block w-full rounded-lg border-2 border-black bg-white px-3 py-2.5 text-center font-mono text-lg font-bold tracking-[0.3em] text-gray-900 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/30 dark:bg-gray-900 dark:text-gray-100"
                            placeholder="••••"
                        />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            wire:click="cancelRemoveWithAuth"
                            wire:loading.attr="disabled"
                            wire:target="cancelRemoveWithAuth,confirmRemoveWithAuth"
                            class="rounded-lg border-2 border-gray-300 bg-white px-3 py-2 text-sm font-bold text-gray-700 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                        >
                            {{ __('rad_table.cloture_cancel') }}
                        </button>
                        <button
                            type="button"
                            wire:click="confirmRemoveWithAuth"
                            wire:loading.attr="disabled"
                            wire:target="confirmRemoveWithAuth"
                            class="rounded-lg border-2 border-black bg-emerald-400 px-3 py-2 text-sm font-black text-black shadow-[0_4px_0_0_rgba(0,0,0,1)] active:translate-y-1 active:shadow-none"
                        >
                            {{ __('pos.remove_line') }}
                        </button>
                    </div>
                </x-staff-pin-auth-card>
            </div>
        </div>
    @endif

    {{-- Total + Compact actions --}}
    <div
        class="shrink-0 space-y-1 border-t-4 border-blue-600 bg-white px-1.5 py-1 dark:border-blue-500 dark:bg-slate-900"
    >
        @if ($this->isStaffMealTable && $this->posOrders->isNotEmpty())
            <div class="space-y-1.5 text-[12px] leading-snug landscape:text-[13px] text-gray-800 dark:text-slate-200">
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                    <span class="shrink-0 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.staff_meal_sous_total_ht_screen') }}:</span>
                    <span class="tabular-nums font-bold text-gray-950 dark:text-white">{{ $this->formatMinor($this->staffMealPreDiscountHtMinor) }}</span>
                </div>
                <div class="flex flex-wrap items-baseline justify-between gap-x-2 gap-y-0.5">
                    <span class="shrink-0 font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.staff_meal_tva_label', ['rate' => $this->staffMealReceiptVatRateLabel]) }}:</span>
                    <span class="tabular-nums font-bold text-gray-950 dark:text-white">{{ $this->formatMinor($this->staffMealPreDiscountVatMinor) }}</span>
                </div>
                @if ($this->staffMealShowPricingBreakdown)
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-0.5">
                        <span class="text-base font-bold tabular-nums text-slate-500 line-through decoration-slate-400 landscape:text-lg dark:text-slate-500 dark:decoration-slate-500">{{ $this->formatMinor($this->staffMealGrossMinor) }}</span>
                        <span class="rounded bg-red-600 px-2 py-0.5 text-xs font-black uppercase tracking-widest text-white shadow-[0_0_10px_rgba(220,38,38,0.5)]">{{ __('pos.staff_meal_off_badge') }}</span>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-2 border-t border-dashed border-gray-300 pt-1.5 dark:border-slate-600">
                    <span class="text-sm font-black uppercase tracking-wide text-gray-900 dark:text-white">{{ __('pos.receipt_grand_total') }}</span>
                    <span class="text-2xl font-black uppercase tracking-widest tabular-nums text-amber-500 dark:text-amber-400">{{ $this->formatMinor($this->subtotalMinor) }}</span>
                </div>
            </div>
        @else
            @if ($this->staffMealShowPricingBreakdown)
                <div class="flex items-center justify-between gap-2 text-xs text-gray-600 line-through dark:text-gray-300">
                    <span class="font-medium">{{ __('pos.staff_meal_subtotal_gross') }}</span>
                    <span class="tabular-nums">{{ $this->formatMinor($this->staffMealGrossMinor) }}</span>
                </div>
                <div class="flex items-center justify-between gap-2 text-xs font-semibold text-emerald-800 dark:text-emerald-200">
                    <span>{{ __('pos.staff_meal_discount_line') }}</span>
                    <span class="tabular-nums">−{{ $this->formatMinor($this->staffMealDiscountMinor) }}</span>
                </div>
            @endif
            <div class="flex items-center justify-between gap-1.5 text-xs text-gray-900 dark:text-gray-100 sm:text-sm">
                <span class="font-medium">{{ __('pos.subtotal') }}</span>
                <span class="text-sm font-bold tabular-nums text-gray-950 sm:text-base dark:text-white">
                    {{ $this->formatMinor($this->subtotalMinor) }}
                </span>
            </div>
        @endif
        <div class="grid grid-cols-2 items-center gap-1.5 sm:gap-2">
            <button
                type="button"
                wire:click="printAddition"
                @disabled(! $this->canImprimerAddition || $footerLocked)
                wire:loading.attr="disabled"
                wire:target="printAddition"
                class="flex h-14 w-14 min-h-11 min-w-11 flex-col items-center justify-center justify-self-start rounded-lg border-2 border-orange-900 bg-orange-500 text-white shadow-md hover:bg-orange-600 focus:ring-2 focus:ring-orange-300 disabled:cursor-not-allowed disabled:opacity-50 sm:h-16 sm:w-16"
                title="{{ __('pos.action_addition_bill') }}"
            >
                <svg class="h-5 w-5 text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 7h16"></path>
                    <path d="M4 12h16"></path>
                    <path d="M4 17h16"></path>
                    <path d="M17 4v16"></path>
                </svg>
                <span class="mt-1 text-[9px] font-extrabold uppercase tracking-wide text-white">Addition</span>
            </button>
            <button
                type="button"
                wire:click="checkoutSession"
                @disabled(! $this->canCloture || $footerLocked)
                wire:loading.attr="disabled"
                wire:target="checkoutSession"
                class="flex h-14 w-14 min-h-11 min-w-11 flex-col items-center justify-center justify-self-end rounded-lg border-2 border-pink-900 bg-pink-500 text-yellow-300 shadow-md hover:bg-pink-600 focus:ring-2 focus:ring-pink-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:border-pink-900 disabled:bg-pink-500 disabled:text-yellow-300 sm:h-16 sm:w-16"
            >
                <svg class="h-5 w-5 text-yellow-300" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="11" width="16" height="9" rx="1.5"></rect>
                    <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                    <path d="M12 14.5v2"></path>
                </svg>
                <span
                    wire:loading.remove
                    wire:target="checkoutSession"
                    class="mt-1 text-[9px] font-extrabold uppercase tracking-wide text-yellow-300"
                >{{ __('pos.action_cloture') }}</span>
                <span
                    wire:loading
                    wire:target="checkoutSession"
                    class="mt-1 text-[9px] font-extrabold uppercase tracking-wide text-yellow-300"
                >...</span>
            </button>
        </div>
        <div
            class="grid grid-cols-1 gap-1"
            wire:loading.class="opacity-60"
            wire:target="printAddition,confirmOrders,checkoutSession"
        >
            <button
                type="button"
                wire:click="printAddition"
                @disabled(! $this->canImprimerAddition || $footerLocked)
                wire:loading.attr="disabled"
                wire:target="printAddition"
                class="min-h-9 rounded-md border-2 border-sky-900 bg-sky-100 py-1 text-center text-[10px] font-extrabold uppercase tracking-wide text-sky-950 shadow-sm hover:bg-sky-200 focus:ring-2 focus:ring-sky-200 disabled:cursor-not-allowed disabled:opacity-50 sm:py-1.5 sm:text-[11px]"
            >
                <span
                    wire:loading.remove
                    wire:target="printAddition"
                >{{ __('pos.action_addition_bill') }}</span>
                <span
                    wire:loading
                    wire:target="printAddition"
                >{{ __('pos.ui_working') }}</span>
            </button>
        </div>
        @if ($this->isBilledState)
            <!-- <p class="text-[10px] text-amber-700 dark:text-amber-300">
                {{ __('rad_table.badge_printed') }}: {{ __('pos.action_cloture') }} を押して会計を完了してください。
            </p> -->
        @endif
    </div>
@endif

    @if ($addModalOpen)
        <div
            class="fixed inset-0 {{ $zAddModal }} flex items-end justify-center sm:items-center"
            style="isolation: isolate"
            role="dialog"
            aria-modal="true"
        >
            <div
                class="absolute inset-0 bg-slate-950/70"
                wire:click="closeAddModal"
            ></div>
            <div
                @click.stop
                class="relative {{ $zAddModalPanel }} m-0 flex max-h-[90dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-2xl border-4 border-blue-600 bg-white text-slate-950 shadow-2xl sm:m-4 sm:rounded-2xl dark:border-blue-500 dark:bg-slate-900 dark:text-white"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b-4 border-blue-600 bg-blue-100 px-3 py-2.5 dark:border-blue-500 dark:bg-blue-950/40"
                >
                    <h3 class="text-sm font-bold">
                        @if ($addModalStep === 'config')
                            {{ $this->addItemForConfig?->name }}
                        @else
                            {{ __('pos.add_modal_title') }}
                        @endif
                    </h3>
                    <button
                        type="button"
                        wire:click="closeAddModal"
                        wire:loading.attr="disabled"
                        wire:target="closeAddModal"
                        class="rounded border border-slate-400 bg-white px-2 py-0.5 text-sm font-bold text-slate-900 hover:bg-slate-100 dark:border-slate-500 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
                    >
                        {{ __('pos.add_modal_close') }}
                    </button>
                </div>
                <div
                    class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2"
                >
                    @if ($addModalStep === 'config' && $this->addItemForConfig)
                        @php
                            $i = $this->addItemForConfig;
                            $stylesL = $this->getStylesListForItem($i);
                            $topsL = $this->getToppingsListForItem($i);
                            $styleReq = $this->isStyleRequiredFor($i);
                        @endphp
                        <p class="mb-2 text-xs text-gray-600 dark:text-gray-300">
                            {{ $i->name }} ·
                            <span class="font-semibold tabular-nums text-gray-900 dark:text-gray-100">
                                {{ $this->formatMinor((int) $i->from_price_minor) }}
                            </span>
                        </p>
                        @if (count($stylesL) > 0)
                            <p
                                class="mb-1 text-xs font-bold uppercase text-gray-800 dark:text-gray-200"
                            >
                                {{ __('pos.add_select_style') }}
                            </p>
                            <ul class="mb-2 space-y-1">
                                @foreach ($stylesL as $s)
                                    <li>
                                        <label
                                            class="flex items-center justify-between gap-2 rounded border border-gray-200 px-2 py-1.5 text-sm dark:border-gray-600"
                                        >
                                            <span
                                                class="inline-flex items-center gap-2 text-gray-900 dark:text-white"
                                            >
                                                <input
                                                    type="radio"
                                                    class="h-3.5 w-3.5"
                                                    name="add-style"
                                                    value="{{ $s['id'] }}"
                                                    wire:model="addStyleId"
                                                />
                                                <span>{{ $s['name'] }}</span>
                                            </span>
                                            <span
                                                class="shrink-0 text-xs font-medium tabular-nums text-gray-800 dark:text-gray-200"
                                            >{{ $s['price_label'] }}</span>
                                        </label>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        @if ($styleReq && count($stylesL) > 0 && ($addStyleId === null || $addStyleId === ''))
                            <p class="mb-2 text-xs text-amber-800 dark:text-amber-200">
                                {{ __('pos.add_style_required_hint') }}
                            </p>
                        @endif
                        @if (count($topsL) > 0)
                            <p
                                class="mb-1 text-xs font-bold uppercase text-gray-800 dark:text-gray-200"
                            >
                                {{ __('pos.add_select_toppings') }}
                            </p>
                            <ul class="mb-2 space-y-0.5">
                                @foreach ($topsL as $t)
                                    @php
                                        $tChecked = in_array(
                                            (string) $t['id'],
                                            $addToppings,
                                            true,
                                        );
                                    @endphp
                                    <li>
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-2 rounded border-2 border-amber-500 bg-amber-50 px-2 py-1.5 text-left text-sm font-semibold text-slate-900 hover:bg-amber-100 focus:ring-2 focus:ring-amber-400 dark:border-amber-500 dark:bg-amber-950/30 dark:text-white dark:hover:bg-amber-900/40"
                                            wire:click="toggleAddTopping('{{ $t['id'] }}')"
                                            wire:loading.attr="disabled"
                                            wire:target="toggleAddTopping"
                                        >
                                            <span
                                                @class(['font-semibold' => $tChecked])
                                            >{{ $tChecked ? '☑' : '☐' }} {{ $t['name'] }}</span>
                                            <span
                                                class="shrink-0 text-xs font-medium tabular-nums text-gray-800 dark:text-gray-200"
                                            >+{{ $t['price_label'] }}</span>
                                        </button>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                        <div
                            class="mb-2 grid grid-cols-1 gap-2 sm:grid-cols-2"
                        >
                            <div>
                                <label
                                    class="mb-0.5 block text-xs font-medium text-gray-800 dark:text-gray-200"
                                >{{ __('pos.add_qty') }}</label>
                                <input
                                    type="number"
                                    class="w-full min-h-10 rounded border border-gray-300 bg-white px-2 text-sm text-gray-950 focus:ring-2 focus:ring-amber-500 dark:border-gray-500 dark:bg-gray-800 dark:text-white"
                                    min="1"
                                    max="200"
                                    step="1"
                                    wire:model.blur="addQty"
                                />
                            </div>
                            <div>
                                <label
                                    class="mb-0.5 block text-xs font-medium text-gray-800 dark:text-gray-200"
                                >{{ __('pos.add_note') }}</label>
                                <input
                                    type="text"
                                    class="w-full min-h-10 rounded border border-gray-300 bg-white px-2 text-sm text-gray-950 focus:ring-2 focus:ring-amber-500 dark:border-gray-500 dark:bg-gray-800 dark:text-white"
                                    wire:model="addNote"
                                />
                            </div>
                        </div>
                    @elseif ($addModalStep === 'config' && $this->addItemForConfig === null)
                        <p
                            class="text-sm text-gray-800 dark:text-gray-200"
                        >{{ __('pos.add_item_load_error') }}</p>
                    @endif

                    @if ($addModalStep === 'list')
                        <div class="space-y-2">
                            @if (count($addCatalog) === 0)
                                <p
                                    class="text-sm text-gray-800 dark:text-gray-200"
                                >{{ __('pos.add_no_menu') }}</p>
                            @endif
                            @foreach ($addCatalog as $block)
                                <div>
                                    <p
                                        class="mb-0.5 text-xs font-bold uppercase text-gray-700 dark:text-gray-200"
                                    >{{ $block['name'] }}</p>
                                    <ul class="space-y-0.5">
                                        @foreach ($block['items'] as $m)
                                            <li>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center justify-between gap-2 rounded border-2 border-blue-400 bg-white py-1.5 text-left text-sm font-semibold hover:bg-blue-50 focus:ring-2 focus:ring-blue-400 dark:border-blue-600 dark:bg-slate-800 dark:hover:bg-slate-700"
                                                    wire:click="beginConfigureItem({{ (int) $m['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="beginConfigureItem"
                                                >
                                                    <span
                                                        class="min-w-0 truncate text-gray-950 dark:text-white"
                                                    >{{ $m['name'] }}</span>
                                                    <span
                                                        class="shrink-0 text-xs font-semibold tabular-nums text-gray-800 dark:text-gray-100"
                                                    >{{ $m['from_label'] }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
                <div
                    class="flex shrink-0 gap-2 border-t-4 border-blue-600 bg-white px-3 py-2.5 dark:border-blue-500 dark:bg-slate-900"
                >
                    @if ($addModalStep === 'config')
                        <button
                            type="button"
                            wire:click="backToAddList"
                            wire:loading.attr="disabled"
                            wire:target="backToAddList"
                            class="min-h-10 flex-1 rounded-md border-2 border-slate-600 bg-white py-2 text-sm font-extrabold uppercase tracking-wide text-slate-900 hover:bg-slate-100 dark:border-slate-500 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
                        >{{ __('pos.add_back') }}</button>
                        <button
                            type="button"
                            wire:click="submitAddLine"
                            wire:loading.attr="disabled"
                            wire:target="submitAddLine"
                            class="min-h-10 flex-1 rounded-md border-2 border-amber-950 bg-amber-500 py-2 text-sm font-extrabold uppercase tracking-wide text-slate-950 hover:bg-amber-600"
                        >{{ __('pos.add_submit') }}</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($showReceiptPreview && $previewSessionId > 0)
        <livewire:pos.receipt-preview
            :shop-id="$this->shopId"
            :table-session-id="$previewSessionId"
            :intent="$previewIntent"
            :expected-session-revision="$expectedSessionRevision"
            :key="'pos-receipt-preview-'.$this->shopId.'-'.$previewSessionId.'-'.$previewIntent.'-'.$expectedSessionRevision"
        />
    @endif
</div>
