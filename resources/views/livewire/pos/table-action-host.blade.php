@php
    $open = $this->activeRestaurantTableId !== null;
    $footerLocked = $this->footerActionsLocked;
    $echoShopId = (int) $this->shopId;
    $initialSnapshotScriptId = 'pos-initial-snapshot-'.$this->getId();
    $initialSnapshotPayload = $this->getGlobalSnapshotPayload();
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
    @class([
        'fi-no-print flex min-h-0 w-full min-w-0 max-w-full flex-col border-s-4 border-blue-600 bg-linear-to-b from-white via-blue-50 to-blue-100 text-gray-950 dark:border-blue-500 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950 dark:text-gray-100',
        'h-full',
    ])
    x-data="{
        isLocalSkeletonVisible: false,
        localSkeletonToken: null,
        previewTableName: '',
        previewSessionId: null,
        /**
         * Line list surface: skeleton (Phase1 pulse) | afterimage (LRU hit, HIT_STALE) | live (Livewire posOrders).
         * Authoritative alone does not leave afterimage unless selfActionPending (own write / Sync / first-load skeleton).
         */
        lineSurfaceMode: 'live',
        afterimageLines: [],
        changeTableModalOpen: false,
        /** Next authoritative may reveal Livewire lines (user/Sync/first skeleton load). */
        selfActionPending: false,
        seenUnsentLineKeys: {},
        bulkSyncing: false,
        lastSyncedAt: '--:--',
        closeDrawer() {
            this.seenUnsentLineKeys = {};
            if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
                window.Livewire.dispatch('pos-tile-interaction-ended');
            }
            this.isLocalSkeletonVisible = false;
            this.localSkeletonToken = null;
            this.previewTableName = '';
            this.previewSessionId = null;
            this.lineSurfaceMode = 'live';
            this.afterimageLines = [];
            this.changeTableModalOpen = false;
            this.selfActionPending = false;
            $wire.closeHost();
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
        scrollAddCatalogToCategory(catId) {
            const el = document.getElementById('pos-add-category-' + catId);
            if (el) {
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        },
    }"
    x-init="
        (function () {
            const shopId = {{ $echoShopId }};
            const hydrateAfterimageFromGlobalSnapshot = function (snapshot) {
                const s = window.Alpine?.store?.('posDraft');
                if (!s || typeof s.writeAfterimageFromAuthoritative !== 'function') {
                    return;
                }
                const rows = Array.isArray(snapshot && snapshot.tables) ? snapshot.tables : [];
                rows.forEach(function (row) {
                    s.writeAfterimageFromAuthoritative({
                        shopId: snapshot && snapshot.shopId,
                        restaurantTableId: row && row.restaurantTableId,
                        tableSessionId: row && row.tableSessionId,
                        lines: Array.isArray(row && row.lines) ? row.lines : [],
                    });
                });
            };
            const hydrateFromScriptTag = function () {
                const el = document.getElementById(@js($initialSnapshotScriptId));
                if (!el) {
                    return;
                }
                try {
                    const payload = JSON.parse(el.textContent || '{}');
                    hydrateAfterimageFromGlobalSnapshot(payload);
                } catch (_) {}
            };
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
            hydrateFromScriptTag();
            window.__posSnapshotRefreshBound = window.__posSnapshotRefreshBound || {};
            if (!window.__posSnapshotRefreshBound[shopId]) {
                window.__posSnapshotRefreshBound[shopId] = true;
                window.addEventListener('pos-snapshot-refreshed', function (event) {
                    const d = event && event.detail ? event.detail : {};
                    const t = d && typeof d === 'object' ? d.table : null;
                    if (!t) {
                        return;
                    }
                    const s = window.Alpine?.store?.('posDraft');
                    if (!s || typeof s.writeAfterimageFromAuthoritative !== 'function') {
                        return;
                    }
                    s.writeAfterimageFromAuthoritative({
                        shopId: d.shopId,
                        restaurantTableId: t.restaurantTableId,
                        tableSessionId: t.tableSessionId,
                        lines: Array.isArray(t.lines) ? t.lines : [],
                    });
                });
                window.addEventListener('pos-snapshot-full-updated', function (event) {
                    const d = event && event.detail ? event.detail : {};
                    const s = window.Alpine?.store?.('posDraft');
                    if (!s || typeof s.writeAfterimageFromAuthoritative !== 'function') {
                        return;
                    }
                    const rows = Array.isArray(d && d.tables) ? d.tables : [];
                    rows.forEach(function (row) {
                        s.writeAfterimageFromAuthoritative({
                            shopId: d.shopId,
                            restaurantTableId: row && row.restaurantTableId,
                            tableSessionId: row && row.tableSessionId,
                            lines: Array.isArray(row && row.lines) ? row.lines : [],
                        });
                    });
                    const generatedAt = d && typeof d.generatedAt === 'string' ? d.generatedAt : '';
                    const parsed = generatedAt ? new Date(generatedAt) : null;
                    const now = parsed && !Number.isNaN(parsed.getTime()) ? parsed : new Date();
                    lastSyncedAt = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                });
            }
            window.addEventListener('EchoLoaded', bind);
        })();
    "
    x-on:show-local-skeleton.window="
        const detail = $event.detail || {}
        try {
            performance.mark('pos-tap-preview')
        } catch (e) {}
        localSkeletonToken = detail.token ?? Date.now()
        previewTableName = typeof detail.tableName === 'string' ? detail.tableName : ''
        const rawSid = detail.sessionId
        previewSessionId =
            typeof rawSid === 'number' && Number.isFinite(rawSid) && rawSid > 0
                ? rawSid
                : null
        const shopId = {{ $echoShopId }}
        const tidRaw = detail.tid
        const tid =
            tidRaw != null && tidRaw !== '' && Number.isFinite(Number(tidRaw)) ? Number(tidRaw) : 0
        if (previewSessionId === null || shopId < 1 || tid < 1) {
            lineSurfaceMode = 'skeleton'
            isLocalSkeletonVisible = true
            afterimageLines = []
            selfActionPending = true
        } else {
            const s = window.Alpine && typeof Alpine.store === 'function' ? Alpine.store('posDraft') : null
            const key = String(shopId) + ':' + String(tid) + ':' + String(previewSessionId)
            const hit = s && typeof s.readAfterimage === 'function' ? s.readAfterimage(key) : null
            if (hit != null && Array.isArray(hit.lines)) {
                lineSurfaceMode = 'afterimage'
                isLocalSkeletonVisible = false
                selfActionPending = false
                afterimageLines = hit.lines
                    .map(function (l) {
                        return {
                            id: l.id,
                            name: l.name,
                            qty: l.qty,
                            summary: l.summary,
                            is_unsent: Boolean(l.is_unsent),
                        }
                    })
                    .sort(function (a, b) {
                        if (a.is_unsent !== b.is_unsent) {
                            return a.is_unsent ? -1 : 1
                        }
                        return a.id - b.id
                    })
            } else {
                lineSurfaceMode = 'skeleton'
                isLocalSkeletonVisible = true
                afterimageLines = []
                selfActionPending = true
            }
        }
    "
    x-on:pos-tile-interaction-started.window="
        if (lineSurfaceMode === 'afterimage') {
            return
        }
        if (!isLocalSkeletonVisible) {
            localSkeletonToken = Date.now()
            isLocalSkeletonVisible = true
            lineSurfaceMode = 'skeleton'
        }
    "
    x-on:pos-action-host-ui-sync.window="
        if (lineSurfaceMode !== 'skeleton') {
            isLocalSkeletonVisible = false
        }
        previewTableName = ''
        previewSessionId = null
        localSkeletonToken = null
        try {
            performance.mark('pos-host-ui-sync')
        } catch (e) {}
    "
    x-on:pos-action-host-authoritative.window="
        const detail = $event.detail || {}
        const incomingSid =
            typeof detail.tableSessionId === 'number'
            && Number.isFinite(detail.tableSessionId)
            && detail.tableSessionId > 0
                ? detail.tableSessionId
                : null
        if (selfActionPending) {
            lineSurfaceMode = 'live'
            afterimageLines = []
            selfActionPending = false
            isLocalSkeletonVisible = false
            return
        }
        const curRaw = $wire.activeTableSessionId
        const curSid =
            typeof curRaw === 'number' && Number.isFinite(curRaw) && curRaw > 0
                ? curRaw
                : (curRaw != null && curRaw !== '' && Number.isFinite(Number(curRaw)) && Number(curRaw) > 0
                    ? Number(curRaw)
                    : null)
        if (incomingSid !== null && curSid !== null && incomingSid !== curSid) {
            return
        }
        const rawAuthoritative = Array.isArray(detail.lines) ? detail.lines : []
        const mappedAuthoritative = rawAuthoritative
            .map(function (row) {
                const id = Number(row && row.id)
                const qty = Number(row && row.qty)
                return {
                    id: Number.isFinite(id) && id > 0 ? id : 0,
                    name: typeof (row && row.name) === 'string' ? row.name : '',
                    qty: Number.isFinite(qty) ? qty : 0,
                    summary: typeof (row && row.summary) === 'string' ? row.summary : '',
                    is_unsent: Boolean(row && row.is_unsent),
                }
            })
            .filter(function (r) {
                return r.id > 0
            })
        mappedAuthoritative.sort(function (a, b) {
            if (a.is_unsent !== b.is_unsent) {
                return a.is_unsent ? -1 : 1
            }
            return a.id - b.id
        })
        afterimageLines = mappedAuthoritative
        isLocalSkeletonVisible = false
        if (lineSurfaceMode === 'afterimage') {
            return
        }
        lineSurfaceMode = 'live'
        afterimageLines = []
    "
    x-on:pos-afterimage-self-action.window="selfActionPending = true"
    x-on:pos-afterimage-sync-request.window="selfActionPending = true"
    x-on:pos-draft-context-switched.window="seenUnsentLineKeys = {}"
    x-on:pos-change-table-modal-open.window="
        const sid = Number($wire.activeTableSessionId || 0)
        if (sid > 0) {
            changeTableModalOpen = true
        }
    "
    x-on:pos-tile-interaction-ended.window="
        isLocalSkeletonVisible = false
        localSkeletonToken = null
        previewTableName = ''
        previewSessionId = null
        lineSurfaceMode = 'live'
        afterimageLines = []
        selfActionPending = false
    "
>
    <script type="application/json" id="{{ $initialSnapshotScriptId }}">
        {!! json_encode($initialSnapshotPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
    @if (! $open)
        <div
            wire:key="pane-welcome"
            x-cloak
            class="flex flex-1 flex-col items-center justify-center gap-2 p-6 text-center"
        >
            <p class="text-sm font-medium text-gray-800 dark:text-gray-100">
                {{ __('pos.detail_pick_table') }}
            </p>
        </div>
    @else
        <div wire:key="pane-real-content" x-cloak class="flex min-h-0 flex-1 flex-col">
        {{-- Header: table name + primary actions（max-md は2段＋コンパクトで視認性優先） --}}
        <div
            class="flex shrink-0 flex-col gap-1 border-b-4 border-blue-600 bg-white px-1.5 py-1 dark:border-blue-500 dark:bg-slate-900 max-md:gap-1 md:gap-1.5 md:flex-row md:items-center md:justify-between"
        >
            <div class="flex min-w-0 w-full items-center gap-1 md:flex-1">
                <button
                    type="button"
                    wire:click="closeHost"
                    wire:loading.attr="disabled"
                    wire:target="closeHost"
                    class="me-0.5 inline-flex h-7 w-7 shrink-0 touch-manipulation items-center justify-center rounded-md border-2 border-slate-400 bg-white text-gray-950 hover:bg-slate-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400 md:h-9 md:w-9 md:hidden dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
                    aria-label="{{ __('pos.detail_pick_table') }}"
                >
                    <svg class="h-3.5 w-3.5 shrink-0 md:h-5 md:w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <polyline points="13,4 7,10 13,16" />
                    </svg>
                </button>
                <div class="min-w-0 flex-1">
                    <div class="mt-0.5 flex min-w-0 items-center gap-1 max-md:gap-1 md:gap-1.5">
                        <span class="inline-flex min-w-0 max-w-full rounded-full border-2 border-amber-400 bg-amber-100 px-3 py-1 text-[13px] font-extrabold uppercase tracking-wide text-blue-900 max-md:px-3 max-md:py-1 max-md:text-[13px] md:px-4 md:text-[14px] md:tracking-wider dark:border-blue-500 dark:bg-blue-950/50 dark:text-blue-100">
                            <span
                                class="min-w-0 max-w-full truncate text-blue-900 dark:text-blue-100"
                                x-show="previewTableName"
                                x-text="previewTableName"
                            ></span>
                            <span
                                class="min-w-0 max-w-full truncate text-blue-900 dark:text-blue-100"
                                x-show="!previewTableName"
                            >{{ $this->activeSessionLabel }}</span>
                        </span>
                        <button
                            type="button"
                            wire:click="manualSyncAllTables"
                            wire:loading.attr="disabled"
                            wire:target="manualSyncAllTables"
                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border-2 border-emerald-900 bg-emerald-500 text-white shadow-sm hover:bg-emerald-600 focus:outline-none focus:ring-2 focus:ring-emerald-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-emerald-400"
                            title="Sync"
                            aria-label="Sync"
                        >
                            <svg
                                class="h-3.5 w-3.5"
                                wire:loading.class="animate-spin"
                                wire:target="manualSyncAllTables"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M21 12a9 9 0 1 1-2.64-6.36" />
                                <polyline points="21 3 21 9 15 9" />
                            </svg>
                        </button>
                        @if ($this->isBilledState)
                            <span class="shrink-0 rounded-full border-2 border-amber-500 bg-amber-200 px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide text-amber-900 max-md:px-1.5 max-md:text-[9px] md:px-2 md:text-[10px] dark:border-amber-400 dark:bg-amber-900/50 dark:text-amber-100">
                                {{ __('rad_table.badge_printed') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>
            <div class="grid w-full shrink-0 grid-cols-2 gap-1 max-md:gap-1 md:flex md:w-auto md:items-center md:gap-1.5">
                <button
                    type="button"
                    wire:click="ajouter"
                    data-pos-ajouter-primary
                    x-bind:disabled="isLocalSkeletonVisible || @js($footerLocked)"
                    wire:loading.attr="disabled"
                    wire:target="ajouter"
                    class="touch-manipulation inline-flex min-h-9 shrink-0 items-center justify-center rounded-md border-2 border-sky-950 bg-sky-400 px-1 py-1 text-[9px] font-extrabold uppercase leading-tight tracking-wide text-gray-950 shadow-md hover:bg-sky-300 focus:outline-none focus:ring-2 focus:ring-sky-200 disabled:cursor-not-allowed disabled:opacity-50 max-md:min-h-9 max-md:px-1 max-md:py-1 max-md:text-[9px] md:min-h-[52px] md:px-4 md:py-2.5 md:text-sm"
                >
                    {{ __('pos.action_ajouter') }}
                </button>
                <button
                    type="button"
                    x-on:click="
                        if (bulkSyncing || $wire.uiState === 'in_flight') { return; }
                        bulkSyncing = true;
                        const s = Alpine.store('posDraft');
                        $wire.bulkAddAndConfirm([]).then(() => {
                            if (s && s.shopId && s.sessionId) {
                                s.clearSession(s.shopId, s.sessionId, true);
                            }
                        }).finally(() => {
                            bulkSyncing = false;
                        });
                    "
                    x-bind:disabled="
                        isLocalSkeletonVisible
                        || @js(($this->activeTableSessionId === null || $this->session === null) || $footerLocked)
                    "
                    wire:loading.attr="disabled"
                    wire:target="bulkAddAndConfirm"
                    class="touch-manipulation inline-flex min-h-9 shrink-0 items-center justify-center rounded-md border-2 border-blue-950 bg-blue-500 px-1 py-1 text-[9px] font-extrabold uppercase leading-tight tracking-wide text-white shadow-md hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 max-md:min-h-9 max-md:px-1 max-md:py-1 max-md:text-[9px] md:min-h-[52px] md:px-4 md:py-2.5 md:text-sm"
                >
                    <span
                        wire:loading.remove
                        wire:target="bulkAddAndConfirm"
                        class="max-md:line-clamp-2 max-md:text-center max-md:leading-tight"
                    >{{ __('pos.action_recu_staff') }}</span>
                    <span
                        wire:loading
                        wire:target="bulkAddAndConfirm"
                    >{{ __('pos.ui_working') }}</span>
                </button>
            </div>
        </div>

        <div
            class="relative min-h-0 flex-1 overflow-y-auto overscroll-contain px-1 py-[2px] max-md:min-h-[min(48svh,22rem)]"
        >
            <div
                x-show="lineSurfaceMode === 'skeleton' && isLocalSkeletonVisible"
                x-cloak
                class="absolute inset-x-1 inset-y-[2px] z-20 flex min-h-[6.5rem] flex-col gap-1 rounded-md border border-slate-200/80 bg-linear-to-b from-white via-white to-blue-50/90 px-1 py-2 shadow-sm dark:border-slate-600/80 dark:from-slate-900 dark:via-slate-900 dark:to-slate-950/90"
                aria-hidden="true"
            >
                <div class="h-6 w-full animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                <div class="h-6 w-11/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                <div class="h-6 w-10/12 animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
                <div class="h-6 w-full max-w-[92%] animate-pulse rounded-md bg-slate-200/90 dark:bg-slate-700/70"></div>
            </div>
            <div
                x-show="lineSurfaceMode === 'afterimage'"
                x-cloak
                class="relative z-10 min-h-0 space-y-1 px-0.5 pb-2 pt-1"
                role="status"
                aria-live="polite"
            >
                <template x-for="row in afterimageLines" :key="row.id">
                    <div
                        class="grid grid-cols-[auto_1fr] items-start gap-x-1 rounded-md border px-2 py-1 text-[12px] shadow-sm"
                        :class="row.is_unsent
                            ? 'border-rose-200 bg-rose-50 dark:border-rose-800 dark:bg-rose-950/20'
                            : 'border-slate-200 bg-slate-100 opacity-90 dark:border-slate-700 dark:bg-slate-900/60'"
                    >
                        <button
                            type="button"
                            x-on:click="$wire.promptRemoveLine(Number(row.id || 0))"
                            x-bind:disabled="!row.id || @js($footerLocked)"
                            class="row-span-1 mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center self-start rounded border border-slate-400 bg-slate-200 text-[10px] font-bold text-slate-700 hover:bg-slate-300 focus:ring-1 focus:ring-slate-300 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-600 dark:bg-slate-700 dark:text-slate-200 dark:hover:bg-slate-600"
                            title="{{ __('pos.remove_line') }}"
                        >
                            ×
                        </button>
                        <div
                            class="leading-tight"
                            :class="row.is_unsent
                                ? 'font-extrabold text-gray-950 dark:text-white'
                                : 'font-medium text-slate-700 dark:text-slate-200'"
                        >
                            <span class="tabular-nums" x-text="row.qty"></span>
                            <span class="ms-1" x-text="row.name"></span>
                        </div>
                        <p
                            class="col-start-2 mt-0.5 text-[11px] leading-snug"
                            :class="row.is_unsent ? 'text-gray-700 dark:text-gray-200' : 'text-slate-600 dark:text-slate-300'"
                            x-show="row.summary"
                            x-text="row.summary"
                        ></p>
                    </div>
                </template>
                <p
                    x-show="afterimageLines.length === 0"
                    class="px-1 text-center text-sm text-slate-600 dark:text-slate-300"
                >
                    {{ __('pos.drawer_no_orders') }}
                </p>
                <span
                    class="pointer-events-none absolute bottom-1 right-1 z-10 inline-flex h-4 w-4 items-center justify-center rounded-full bg-amber-100/45 text-[10px] text-amber-800/65 dark:bg-amber-950/30 dark:text-amber-200/50"
                    aria-hidden="true"
                    title="{{ __('pos.afterimage_syncing') }}"
                >
                    🔄
                </span>
            </div>
            <div x-show="lineSurfaceMode === 'live'" class="min-h-0">
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
                {{--
                  live: 商品表示は posOrders（Livewire / DB）のみ。
                  afterimage: posDraft.readAfterimage の LRU ミラー（読取のみ）。submitAddLine は DB 直書きのまま。
                --}}
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
                                            x-bind:disabled="isLocalSkeletonVisible || @js($footerLocked)"
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
                                        x-bind:disabled="isLocalSkeletonVisible || @js($footerLocked)"
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
        <div class="grid grid-cols-2 items-center gap-1.5 sm:gap-2 max-md:gap-1">
            <button
                type="button"
                wire:click="openReceiptPreview('addition')"
                x-bind:disabled="isLocalSkeletonVisible || @js($footerLocked)"
                wire:loading.attr="disabled"
                wire:target="openReceiptPreview"
                class="flex h-14 w-14 min-h-11 min-w-11 flex-col items-center justify-center justify-self-start rounded-lg border-2 border-orange-900 bg-orange-500 text-white shadow-md hover:bg-orange-600 focus:ring-2 focus:ring-orange-300 disabled:cursor-not-allowed disabled:opacity-50 max-md:h-12 max-md:w-12 max-md:min-h-10 max-md:min-w-10 sm:h-16 sm:w-16"
                title="{{ __('pos.action_addition_bill') }}"
            >
                <svg class="h-5 w-5 text-white max-md:h-4 max-md:w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M4 7h16"></path>
                    <path d="M4 12h16"></path>
                    <path d="M4 17h16"></path>
                    <path d="M17 4v16"></path>
                </svg>
                <span class="mt-0.5 text-[8px] font-extrabold uppercase leading-tight tracking-wide text-white max-md:mt-0.5 max-md:text-[8px] sm:mt-1 sm:text-[9px]">Addition</span>
            </button>
            <button
                type="button"
                wire:click="checkoutSession"
                x-bind:disabled="isLocalSkeletonVisible || @js($footerLocked)"
                wire:loading.attr="disabled"
                wire:target="checkoutSession"
                class="flex h-14 w-14 min-h-11 min-w-11 flex-col items-center justify-center justify-self-end rounded-lg border-2 border-pink-900 bg-pink-500 text-yellow-300 shadow-md hover:bg-pink-600 focus:ring-2 focus:ring-pink-300 disabled:cursor-not-allowed disabled:opacity-50 disabled:border-pink-900 disabled:bg-pink-500 disabled:text-yellow-300 max-md:h-12 max-md:w-12 max-md:min-h-10 max-md:min-w-10 sm:h-16 sm:w-16"
            >
                <svg class="h-5 w-5 text-yellow-300 max-md:h-4 max-md:w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="4" y="11" width="16" height="9" rx="1.5"></rect>
                    <path d="M8 11V8a4 4 0 0 1 8 0v3"></path>
                    <path d="M12 14.5v2"></path>
                </svg>
                <span
                    wire:loading.remove
                    wire:target="checkoutSession"
                    class="mt-0.5 text-[8px] font-extrabold uppercase leading-tight tracking-wide text-yellow-300 sm:mt-1 sm:text-[9px]"
                >{{ __('pos.action_cloture') }}</span>
                <span
                    wire:loading
                    wire:target="checkoutSession"
                    class="mt-0.5 text-[8px] font-extrabold uppercase leading-tight tracking-wide text-yellow-300 sm:mt-1 sm:text-[9px]"
                >...</span>
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
                class="relative {{ $zAddModalPanel }} m-0 flex h-[90dvh] max-h-[90dvh] w-full max-w-7xl flex-col overflow-hidden rounded-t-2xl border-4 border-blue-600 bg-white text-slate-950 shadow-2xl sm:m-4 sm:w-[90vw] sm:rounded-2xl dark:border-blue-500 dark:bg-slate-900 dark:text-white"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b-4 border-blue-600 bg-blue-100 px-3 py-2.5 dark:border-blue-500 dark:bg-blue-950/40"
                >
                    <h3 class="min-w-0 truncate text-base font-bold text-gray-950 dark:text-white">
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
                        class="touch-manipulation rounded border border-slate-400 bg-white px-3 py-1.5 text-sm font-bold text-slate-900 hover:bg-slate-100 dark:border-slate-500 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
                    >
                        {{ __('pos.add_modal_close') }}
                    </button>
                </div>
                @if ($addModalStep === 'list')
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div class="flex min-h-0 flex-1 overflow-hidden">
                        @if (count($addCatalog) > 0)
                            <aside
                                class="flex w-[25%] min-w-[10rem] max-w-[14rem] shrink-0 flex-col overflow-y-auto overscroll-contain border-e-4 border-blue-200 bg-slate-50 py-2 ps-2 pe-1.5 dark:border-blue-800 dark:bg-slate-800/80"
                                aria-label="{{ __('pos.add_modal_title') }}"
                            >
                                @foreach ($addCatalog as $block)
                                    <button
                                        type="button"
                                        class="touch-manipulation mb-1 w-full rounded-lg border-2 border-transparent bg-white px-2 py-3 text-left text-sm font-bold leading-snug text-gray-950 shadow-sm hover:border-blue-400 hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 dark:bg-slate-900 dark:text-white dark:hover:border-blue-500 dark:hover:bg-slate-800"
                                        @click.prevent="scrollAddCatalogToCategory({{ (int) $block['id'] }})"
                                    >{{ $block['name'] }}</button>
                                @endforeach
                            </aside>
                        @endif
                        <div
                            @class([
                                'min-h-0 min-w-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2',
                                'w-full' => count($addCatalog) === 0,
                            ])
                        >
                            @if (count($addCatalog) === 0)
                                <p
                                    class="text-base text-gray-800 dark:text-gray-200"
                                >{{ __('pos.add_no_menu') }}</p>
                            @endif
                            @foreach ($addCatalog as $block)
                                <section class="mb-4 last:mb-1">
                                    <h3
                                        id="pos-add-category-{{ (int) $block['id'] }}"
                                        class="scroll-mt-3 border-b-2 border-blue-200 pb-1 text-base font-extrabold uppercase tracking-wide text-gray-950 dark:border-blue-700 dark:text-white"
                                    >{{ $block['name'] }}</h3>
                                    <ul class="mt-2 grid grid-cols-1 gap-2 lg:grid-cols-2">
                                        @foreach ($block['items'] as $m)
                                            <li>
                                                <button
                                                    type="button"
                                                    class="touch-manipulation flex min-h-[80px] w-full items-center justify-between gap-3 rounded-xl border-2 border-blue-400 bg-white px-4 py-3 text-left text-base font-bold text-gray-950 shadow-sm hover:bg-blue-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-400 dark:border-blue-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700"
                                                    wire:click="beginConfigureItem({{ (int) $m['id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="beginConfigureItem"
                                                >
                                                    <span
                                                        class="min-w-0 flex-1 leading-snug text-gray-950 dark:text-white"
                                                    >{{ $m['name'] }}</span>
                                                    <span
                                                        class="shrink-0 text-base font-semibold tabular-nums text-gray-800 dark:text-gray-100"
                                                    >{{ $m['from_label'] }}</span>
                                                </button>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    </div>
                    <div
                        class="flex shrink-0 flex-col gap-2 border-t-4 border-blue-600 bg-white px-3 py-2.5 shadow-[0_-6px_16px_rgba(15,23,42,0.12)] dark:border-blue-500 dark:bg-slate-900 dark:shadow-[0_-6px_16px_rgba(0,0,0,0.35)]"
                    >
                        <p class="text-xs leading-snug text-gray-700 dark:text-gray-200">
                            {{ __('pos.add_modal_list_footer_hint') }}
                        </p>
                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="button"
                                wire:click="closeAddModal"
                                wire:loading.attr="disabled"
                                wire:target="closeAddModal"
                                class="touch-manipulation min-h-12 shrink-0 rounded-md border-2 border-slate-600 bg-white px-3 py-2.5 text-sm font-extrabold uppercase tracking-wide text-slate-900 hover:bg-slate-100 sm:w-40 dark:border-slate-500 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
                            >{{ __('pos.add_modal_close') }}</button>
                            <button
                                type="button"
                                x-on:click="
                                    if (bulkSyncing || $wire.uiState === 'in_flight') { return; }
                                    bulkSyncing = true;
                                    const s = Alpine.store('posDraft');
                                    $wire.bulkAddAndConfirm([]).then(() => {
                                        if (s && s.shopId && s.sessionId) {
                                            s.clearSession(s.shopId, s.sessionId, true);
                                        }
                                    }).finally(() => {
                                        bulkSyncing = false;
                                    });
                                "
                                x-bind:disabled="
                                    bulkSyncing
                                    || isLocalSkeletonVisible
                                    || @js(($this->activeTableSessionId === null || $this->session === null) || $footerLocked)
                                "
                                wire:loading.attr="disabled"
                                wire:target="bulkAddAndConfirm"
                                class="touch-manipulation min-h-12 flex-1 rounded-md border-2 border-blue-950 bg-blue-500 px-3 py-2.5 text-sm font-extrabold uppercase tracking-wide text-white shadow-md hover:bg-blue-600 focus:ring-2 focus:ring-blue-300 disabled:cursor-not-allowed disabled:opacity-50 dark:text-white"
                            >
                                <span
                                    wire:loading.remove
                                    wire:target="bulkAddAndConfirm"
                                >{{ __('pos.action_recu_staff') }}</span>
                                <span
                                    wire:loading
                                    wire:target="bulkAddAndConfirm"
                                >{{ __('pos.ui_working') }}</span>
                            </button>
                        </div>
                    </div>
                    </div>
                @elseif ($addModalStep === 'config')
                    <div
                        wire:key="pos-add-config-touch-{{ (int) ($this->addConfigMenuItemId ?? 0) }}"
                        class="flex min-h-0 flex-1 flex-col overflow-hidden"
                        x-data="{
                            q: {{ max(1, min(200, (int) $this->addQty)) }},
                            dec() { this.q = Math.max(1, this.q - 1); },
                            inc() { this.q = Math.min(200, this.q + 1); },
                        }"
                        x-init="q = {{ max(1, min(200, (int) $this->addQty)) }}"
                    >
                    <div
                        class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-3 py-2"
                    >
                        @if ($this->addItemForConfig)
                            @php
                                $i = $this->addItemForConfig;
                                $stylesL = $this->getStylesListForItem($i);
                                $topsL = $this->getToppingsListForItem($i);
                                $styleReq = $this->isStyleRequiredFor($i);
                            @endphp
                            <p class="mb-2 text-sm text-gray-600 dark:text-gray-300">
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
                                <ul class="mb-2 space-y-2">
                                    @foreach ($stylesL as $s)
                                        <li>
                                            <label
                                                class="flex min-h-14 cursor-pointer touch-manipulation items-center justify-between gap-3 rounded-xl border-2 border-gray-200 bg-white px-4 py-3 text-base active:bg-gray-50 dark:border-gray-600 dark:bg-slate-900 dark:active:bg-slate-800"
                                            >
                                                <span
                                                    class="inline-flex min-w-0 flex-1 items-center gap-3 text-gray-950 dark:text-white"
                                                >
                                                    <input
                                                        type="radio"
                                                        class="size-5 shrink-0 border-gray-400 text-blue-600 focus:ring-2 focus:ring-blue-500 dark:border-gray-500 dark:text-blue-500"
                                                        name="add-style"
                                                        value="{{ $s['id'] }}"
                                                        wire:model="addStyleId"
                                                    />
                                                    <span class="font-semibold leading-snug">{{ $s['name'] }}</span>
                                                </span>
                                                <span
                                                    class="shrink-0 text-sm font-medium tabular-nums text-gray-800 dark:text-gray-200"
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
                                <ul class="mb-2 space-y-2">
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
                                                class="flex min-h-14 w-full touch-manipulation items-center justify-between gap-3 rounded-xl border-2 border-amber-500 bg-amber-50 px-4 py-3 text-left text-base font-bold text-slate-950 hover:bg-amber-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 active:bg-amber-100 dark:border-amber-500 dark:bg-amber-950/30 dark:text-white dark:hover:bg-amber-900/40 dark:active:bg-amber-900/50"
                                                wire:click="toggleAddTopping('{{ $t['id'] }}')"
                                                wire:loading.attr="disabled"
                                                wire:target="toggleAddTopping"
                                            >
                                                <span
                                                    @class([
                                                        'min-w-0 flex flex-1 items-center gap-2 leading-snug',
                                                        'font-extrabold' => $tChecked,
                                                        'font-semibold' => ! $tChecked,
                                                    ])
                                                >
                                                    <span
                                                        @class([
                                                            'inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border-2 text-[20px] font-black leading-none',
                                                            'border-amber-700 bg-amber-300 text-amber-950 dark:border-amber-300 dark:bg-amber-200 dark:text-amber-950' => $tChecked,
                                                            'border-slate-500 bg-white text-slate-700 dark:border-slate-400 dark:bg-slate-800 dark:text-slate-200' => ! $tChecked,
                                                        ])
                                                        aria-hidden="true"
                                                    >{{ $tChecked ? '✓' : '□' }}</span>
                                                    <span class="min-w-0 flex-1">{{ $t['name'] }}</span>
                                                </span>
                                                <span
                                                    class="shrink-0 text-sm font-semibold tabular-nums text-gray-800 dark:text-gray-200"
                                                >+{{ $t['price_label'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            <div class="mb-2 space-y-3">
                                <div wire:ignore>
                                    <label
                                        class="mb-1.5 block text-sm font-extrabold text-gray-950 dark:text-white"
                                    >{{ __('pos.add_qty') }}</label>
                                    <div
                                        class="flex max-w-md items-stretch gap-2"
                                        role="group"
                                        aria-label="{{ __('pos.add_qty') }}"
                                    >
                                        <button
                                            type="button"
                                            class="touch-manipulation flex min-h-14 min-w-14 shrink-0 items-center justify-center rounded-xl border-2 border-slate-600 bg-white text-2xl font-black leading-none text-slate-900 shadow-sm hover:bg-slate-100 active:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700 dark:active:bg-slate-600"
                                            x-on:click="dec()"
                                            aria-label="{{ __('pos.add_qty') }} −1"
                                        >
                                            −
                                        </button>
                                        <div
                                            class="flex min-h-14 min-w-0 flex-1 items-center justify-center rounded-xl border-2 border-amber-600 bg-amber-50 px-3 dark:border-amber-500 dark:bg-amber-950/40"
                                        >
                                            <span
                                                class="text-3xl font-black tabular-nums text-gray-950 dark:text-white"
                                                x-text="q"
                                                aria-live="polite"
                                            ></span>
                                        </div>
                                        <button
                                            type="button"
                                            class="touch-manipulation flex min-h-14 min-w-14 shrink-0 items-center justify-center rounded-xl border-2 border-slate-600 bg-white text-2xl font-black leading-none text-slate-900 shadow-sm hover:bg-slate-100 active:bg-slate-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500 dark:border-slate-500 dark:bg-slate-800 dark:text-white dark:hover:bg-slate-700 dark:active:bg-slate-600"
                                            x-on:click="inc()"
                                            aria-label="{{ __('pos.add_qty') }} +1"
                                        >
                                            +
                                        </button>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="mb-0.5 block text-[10px] font-medium text-slate-500 dark:text-slate-400"
                                        for="pos-add-note-field"
                                    >{{ __('pos.add_note') }}</label>
                                    <input
                                        id="pos-add-note-field"
                                        type="text"
                                        class="w-full max-w-md rounded-md border border-slate-200 bg-slate-50/80 px-2 py-1.5 text-xs text-gray-800 placeholder:text-slate-400 focus:ring-2 focus:ring-amber-500/60 dark:border-slate-600 dark:bg-slate-800/50 dark:text-gray-200 dark:placeholder:text-slate-500"
                                        wire:model.debounce.500ms="addNote"
                                        autocomplete="off"
                                    />
                                </div>
                            </div>
                        @else
                            <p
                                class="text-sm text-gray-800 dark:text-gray-200"
                            >{{ __('pos.add_item_load_error') }}</p>
                        @endif
                    </div>
                    <div
                        class="flex shrink-0 flex-col gap-2 border-t-4 border-blue-600 bg-white px-3 py-2.5 shadow-[0_-6px_16px_rgba(15,23,42,0.12)] dark:border-blue-500 dark:bg-slate-900 dark:shadow-[0_-6px_16px_rgba(0,0,0,0.35)]"
                    >
                        <div class="flex gap-2">
                            <button
                                type="button"
                                wire:click="backToAddList"
                                wire:loading.attr="disabled"
                                wire:target="backToAddList"
                                class="touch-manipulation min-h-12 flex-1 rounded-md border-2 border-slate-600 bg-white py-2.5 text-sm font-extrabold uppercase tracking-wide text-slate-900 hover:bg-slate-100 dark:border-slate-500 dark:bg-slate-800 dark:text-gray-100 dark:hover:bg-slate-700"
                            >{{ __('pos.add_back') }}</button>
                            @if ($this->addItemForConfig)
                                <button
                                    type="button"
                                    x-on:click="$wire.submitAddLine(q)"
                                    x-bind:disabled="typeof q !== 'number' || q < 1"
                                    wire:loading.attr="disabled"
                                    wire:target="submitAddLine"
                                    class="touch-manipulation min-h-14 flex-1 rounded-md border-2 border-amber-950 bg-amber-500 py-3 text-base font-extrabold uppercase tracking-wide text-slate-950 hover:bg-amber-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-400 disabled:cursor-not-allowed disabled:opacity-50"
                                >{{ __('pos.add_submit') }}</button>
                            @endif
                        </div>
                    </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    @if ($showReceiptPreview && $previewSessionId > 0)
        {{-- wire:key を親に持たせ、子 Livewire のマウント境界を pos-refresh-tiles 連鎖の morph から切り離す --}}
        <div
            wire:key="pos-receipt-preview-mount-{{ $this->shopId }}-{{ $previewSessionId }}-{{ $previewIntent }}-{{ $expectedSessionRevision }}"
        >
            <livewire:pos.receipt-preview
                :shop-id="$this->shopId"
                :table-session-id="$previewSessionId"
                :intent="$previewIntent"
                :expected-session-revision="$expectedSessionRevision"
                :key="'pos-receipt-preview-'.$this->shopId.'-'.$previewSessionId.'-'.$previewIntent.'-'.$expectedSessionRevision"
            />
        </div>
    @endif

    <div
        x-cloak
        x-show="changeTableModalOpen"
        class="fixed inset-0 z-[340] flex items-center justify-center bg-black/70 p-3"
        role="dialog"
        aria-modal="true"
    >
        <div class="absolute inset-0" x-on:click="changeTableModalOpen = false"></div>
        <div class="relative z-[345] w-full max-w-md rounded-xl border-2 border-slate-400 bg-white p-3 text-gray-950 shadow-xl dark:border-slate-600 dark:bg-slate-900 dark:text-white">
            <div class="mb-2 rounded-lg border-2 border-rose-700 bg-rose-100 px-2 py-2 text-center shadow-sm dark:border-rose-400 dark:bg-rose-950/60">
                <p class="text-[11px] font-black uppercase tracking-wider text-rose-900 dark:text-rose-100">
                    {{ __('pos.change_table_selected_label') }}
                </p>
                <p class="mt-0.5 animate-pulse text-base font-black uppercase tracking-wide text-rose-950 dark:text-white">
                    {{ $this->activeSessionLabel }}
                </p>
            </div>
            <div class="mb-2 flex items-center justify-between gap-2">
                <p class="text-sm font-extrabold text-gray-950 dark:text-white">{{ __('pos.action_changer_table') }}</p>
                <button
                    type="button"
                    x-on:click="changeTableModalOpen = false"
                    class="rounded border border-slate-400 bg-slate-100 px-2 py-1 text-xs font-bold text-slate-800 hover:bg-slate-200 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 dark:hover:bg-slate-700"
                >
                    {{ __('pos.close') }}
                </button>
            </div>
            <div class="grid grid-cols-2 gap-2">
                @forelse ($this->changeTableCandidates as $candidate)
                    <button
                        type="button"
                        x-on:click="changeTableModalOpen = false; $wire.changeTable({{ (int) $candidate['id'] }})"
                        class="touch-manipulation rounded-md border-2 border-emerald-700 bg-emerald-500 px-2 py-2 text-left text-sm font-bold text-white hover:bg-emerald-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-300"
                    >
                        {{ $candidate['name'] }}
                    </button>
                @empty
                    <p class="col-span-2 text-sm text-gray-700 dark:text-gray-200">
                        {{ __('pos.change_table_no_available') }}
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>
