{{-- POS Livewire timing panel: in-flow footer strip (opaque) when SPEED_TEST / app.speed_test is on --}}
<div
    id="pos-speed-panel-root"
    wire:ignore
    class="fi-pos-speed-panel flex w-full flex-none flex-col border-t border-zinc-600 bg-zinc-900 px-2 py-2 font-mono text-[10px] leading-snug text-zinc-100 shadow-[0_-4px_12px_rgba(0,0,0,0.15)] dark:border-zinc-500 dark:bg-zinc-950 sm:px-3"
    role="status"
    aria-label="POS Livewire timing"
>
    <div class="mb-1 text-[9px] font-bold uppercase tracking-wide text-amber-300">POS speed</div>
    <div
        id="pos-speed-panel-rows"
        class="grid w-full max-w-full grid-cols-2 gap-x-3 gap-y-0.5 sm:grid-cols-4"
    >
        <div class="truncate">wire: <span id="pos-sp-total">—</span></div>
        <div class="truncate">srvΔ: <span id="pos-sp-back">—</span></div>
        <div class="truncate">net~: <span id="pos-sp-net">—</span></div>
        <div class="truncate">morph: <span id="pos-sp-morph">—</span></div>
    </div>
    <div
        id="pos-speed-panel-detail"
        class="mt-2 max-h-40 w-full max-w-full space-y-1.5 overflow-y-auto overscroll-contain border-t border-zinc-700 pt-2 text-[9px] text-zinc-200"
    >
        <div class="break-words">
            <span class="text-zinc-400">🔄 Morphed</span>
            <span id="pos-sp-morphed-list" class="block pl-0.5 text-zinc-100">—</span>
        </div>
        <div class="break-words">
            <span class="text-zinc-400">📢 Dispatches</span>
            <span id="pos-sp-dispatch-list" class="block pl-0.5 text-zinc-100">—</span>
        </div>
        <div class="break-words">
            <span class="text-zinc-400">📦 Payload</span>
            <span id="pos-sp-payload-kb" class="block pl-0.5 text-zinc-100">—</span>
        </div>
    </div>
    <div id="pos-speed-panel-hint" class="mt-1.5 border-t border-zinc-700 pt-1 text-[8px] leading-tight text-zinc-500">
        srvΔ = responseStart−requestStart (TTFB). net~ = wire − srvΔ. morph = first morph（単一HTTP時）→ microtask → setTimeout(0) → 2×rAF. Dispatches =
        <code class="text-zinc-400">components[].effects.dispatches</code> from JSON. Morphed = Livewire
        <code class="text-zinc-400">morphed</code> hook (DOM morph root).
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                try {
                    if (!document.getElementById('pos-speed-panel-root')) {
                        return;
                    }

                    const $ = (id) => document.getElementById(id);

                    function fmt(ms) {
                        if (ms == null || Number.isNaN(ms)) {
                            return '—';
                        }
                        return Math.round(ms) + 'ms';
                    }

                    function readLivewireUpdateTiming() {
                        try {
                            const entries = performance.getEntriesByType('resource');
                            for (let i = entries.length - 1; i >= 0; i--) {
                                const e = entries[i];
                                if (
                                    e.initiatorType !== 'fetch' ||
                                    typeof e.name !== 'string' ||
                                    e.name.toLowerCase().indexOf('livewire') === -1
                                ) {
                                    continue;
                                }
                                const rs = e.responseStart;
                                const rq = e.requestStart;
                                if (rs > 0 && rq > 0 && rs >= rq) {
                                    return rs - rq;
                                }
                            }
                        } catch (e) {
                            /* ignore */
                        }
                        return null;
                    }

                    function extractDispatchNamesFromJson(json) {
                        const names = [];
                        try {
                            const comps = json && json.components;
                            if (!Array.isArray(comps)) {
                                return names;
                            }
                            for (let i = 0; i < comps.length; i++) {
                                const eff = comps[i] && comps[i].effects;
                                if (!eff || !Array.isArray(eff.dispatches)) {
                                    continue;
                                }
                                for (let k = 0; k < eff.dispatches.length; k++) {
                                    const d = eff.dispatches[k];
                                    if (d && d.name) {
                                        names.push(String(d.name));
                                    }
                                }
                            }
                        } catch (e) {
                            /* ignore */
                        }
                        return names;
                    }

                    function estimatePayloadKb(json) {
                        try {
                            if (json == null) {
                                return null;
                            }
                            const s = JSON.stringify(json);
                            if (typeof s !== 'string' || s.length === 0) {
                                return null;
                            }
                            return (s.length / 1024).toFixed(2);
                        } catch (e) {
                            return null;
                        }
                    }

                    function resetRoundDetailUi() {
                        try {
                            const a = $('pos-sp-morphed-list');
                            const b = $('pos-sp-dispatch-list');
                            const c = $('pos-sp-payload-kb');
                            if (a) {
                                a.textContent = '…';
                            }
                            if (b) {
                                b.textContent = '…';
                            }
                            if (c) {
                                c.textContent = '…';
                            }
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function clearRoundDetailUi() {
                        try {
                            const a = $('pos-sp-morphed-list');
                            const b = $('pos-sp-dispatch-list');
                            const c = $('pos-sp-payload-kb');
                            if (a) {
                                a.textContent = '—';
                            }
                            if (b) {
                                b.textContent = '—';
                            }
                            if (c) {
                                c.textContent = '—';
                            }
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function applyRoundDetailUi(morphedMap, dispatchNames, payloadKb) {
                        try {
                            const morphedEl = $('pos-sp-morphed-list');
                            const dispEl = $('pos-sp-dispatch-list');
                            const payEl = $('pos-sp-payload-kb');
                            if (morphedEl) {
                                if (!morphedMap || morphedMap.size < 1) {
                                    morphedEl.textContent = '（なし）';
                                } else {
                                    morphedEl.textContent = Array.from(morphedMap.values()).join(', ');
                                }
                            }
                            if (dispEl) {
                                if (!dispatchNames || dispatchNames.length < 1) {
                                    dispEl.textContent = '（なし）';
                                } else {
                                    dispEl.textContent = dispatchNames.join(', ');
                                }
                            }
                            if (payEl) {
                                if (payloadKb == null) {
                                    payEl.textContent = '—';
                                } else {
                                    payEl.textContent = payloadKb + ' KB (JSON.stringify)';
                                }
                            }
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    let httpInFlight = 0;
                    let lastWire = null;
                    let lastBack = null;
                    let lastNet = null;
                    let lastMorph = null;
                    let activeRound = null;

                    function paintPanel() {
                        try {
                            $('pos-sp-total').textContent = fmt(lastWire);
                            $('pos-sp-back').textContent = fmt(lastBack);
                            $('pos-sp-net').textContent = fmt(lastNet);
                            $('pos-sp-morph').textContent = fmt(lastMorph);
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function scheduleMorphPaintDone(tFirstMorph) {
                        try {
                            if (tFirstMorph == null) {
                                lastMorph = null;
                                paintPanel();
                                return;
                            }
                            queueMicrotask(function () {
                                try {
                                    setTimeout(function () {
                                        try {
                                            requestAnimationFrame(function () {
                                                requestAnimationFrame(function () {
                                                    try {
                                                        lastMorph = performance.now() - tFirstMorph;
                                                        paintPanel();
                                                    } catch (e) {
                                                        /* ignore */
                                                    }
                                                });
                                            });
                                        } catch (e) {
                                            /* ignore */
                                        }
                                    }, 0);
                                } catch (e) {
                                    /* ignore */
                                }
                            });
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function installPosSpeedHooks() {
                        try {
                            if (window.__posSpeedPanelHooksInstalled) {
                                return;
                            }
                            const lw = window.Livewire;
                            if (!lw || typeof lw.hook !== 'function') {
                                return;
                            }
                            window.__posSpeedPanelHooksInstalled = true;

                            lw.hook('request', function (_ref) {
                                try {
                                    const succeed = _ref.succeed;
                                    const fail = _ref.fail;
                                    if (typeof succeed !== 'function') {
                                        return;
                                    }

                                    resetRoundDetailUi();

                                    const round = {
                                        morphed: new Map(),
                                    };
                                    activeRound = round;

                                    httpInFlight++;
                                    const morphSlot = { t0: null };
                                    let offMorph = null;
                                    let offMorphed = null;

                                    function teardownMorphHooks() {
                                        try {
                                            if (typeof offMorph === 'function') {
                                                offMorph();
                                                offMorph = null;
                                            }
                                            if (typeof offMorphed === 'function') {
                                                offMorphed();
                                                offMorphed = null;
                                            }
                                        } catch (e) {
                                            /* ignore */
                                        }
                                    }

                                    function endHttp() {
                                        httpInFlight = Math.max(0, httpInFlight - 1);
                                        teardownMorphHooks();
                                    }

                                    try {
                                        offMorph = lw.hook('morph', function () {
                                            try {
                                                if (httpInFlight === 1 && morphSlot.t0 === null) {
                                                    morphSlot.t0 = performance.now();
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    } catch (e) {
                                        offMorph = null;
                                    }

                                    try {
                                        offMorphed = lw.hook('morphed', function (detail) {
                                            try {
                                                const r = activeRound;
                                                if (!r || !detail) {
                                                    return;
                                                }
                                                const c = detail.component;
                                                if (!c) {
                                                    return;
                                                }
                                                const id = c.id != null ? String(c.id) : '';
                                                const nm = c.name != null ? String(c.name) : '?';
                                                if (id !== '') {
                                                    r.morphed.set(id, nm);
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    } catch (e) {
                                        offMorphed = null;
                                    }

                                    const tRequest = performance.now();

                                    succeed(function (fwd) {
                                        try {
                                            endHttp();
                                            const tSucceed = performance.now();
                                            lastWire = tSucceed - tRequest;

                                            let dispatchNames = [];
                                            let payloadKb = null;
                                            try {
                                                const j = fwd && fwd.json != null ? fwd.json : null;
                                                dispatchNames = extractDispatchNamesFromJson(j);
                                                payloadKb = estimatePayloadKb(j);
                                            } catch (e) {
                                                /* ignore */
                                            }

                                            const morphedMap = round && round.morphed ? round.morphed : new Map();
                                            applyRoundDetailUi(morphedMap, dispatchNames, payloadKb);
                                            activeRound = null;

                                            queueMicrotask(function () {
                                                try {
                                                    const srvDelta = readLivewireUpdateTiming();
                                                    lastBack = srvDelta;
                                                    if (srvDelta != null) {
                                                        lastNet = Math.max(0, lastWire - srvDelta);
                                                    } else {
                                                        lastNet = null;
                                                    }
                                                    paintPanel();
                                                } catch (e) {
                                                    /* ignore */
                                                }
                                            });
                                            paintPanel();
                                            scheduleMorphPaintDone(morphSlot.t0);
                                        } catch (e) {
                                            /* ignore */
                                        }
                                    });

                                    if (typeof fail === 'function') {
                                        fail(function () {
                                            try {
                                                endHttp();
                                                lastMorph = null;
                                                activeRound = null;
                                                clearRoundDetailUi();
                                                paintPanel();
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    }
                                } catch (e) {
                                    /* ignore */
                                }
                            });
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    if (window.Livewire && typeof window.Livewire.hook === 'function') {
                        installPosSpeedHooks();
                    } else {
                        document.addEventListener('livewire:init', installPosSpeedHooks);
                    }
                } catch (e) {
                    /* never break POS */
                }
            })();
        </script>
    @endpush
@endonce
