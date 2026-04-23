{{-- POS Livewire timing panel: in-flow footer strip (opaque) when SPEED_TEST / app.speed_test is on --}}
<div
    id="pos-speed-panel-root"
    wire:ignore
    class="fi-pos-speed-panel flex w-full flex-none flex-col border-t border-zinc-600 bg-zinc-900 px-2 py-1.5 font-mono text-[10px] leading-snug text-zinc-100 shadow-[0_-4px_12px_rgba(0,0,0,0.15)] dark:border-zinc-500 dark:bg-zinc-950"
    role="status"
    aria-label="POS Livewire timing"
>
    <div class="mb-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-300">POS speed</div>
    <div
        id="pos-speed-panel-rows"
        class="grid w-full max-w-full grid-cols-2 gap-x-3 gap-y-0.5 sm:grid-cols-4"
    >
        <div class="truncate">wire: <span id="pos-sp-total">—</span></div>
        <div class="truncate">srvΔ: <span id="pos-sp-back">—</span></div>
        <div class="truncate">net~: <span id="pos-sp-net">—</span></div>
        <div class="truncate">morph: <span id="pos-sp-morph">—</span></div>
    </div>
    <div id="pos-speed-panel-hint" class="mt-1 border-t border-zinc-700 pt-0.5 text-[8px] text-zinc-400">
        srvΔ = responseStart−requestStart (TTFB wait). net~ = wire − srvΔ. morph = first morph（単一HTTP時のみ）→ microtask → 2×rAF.
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

                    let httpInFlight = 0;
                    let lastWire = null;
                    let lastBack = null;
                    let lastNet = null;
                    let lastMorph = null;

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
                                    httpInFlight++;
                                    const morphSlot = { t0: null };
                                    let offMorph = null;
                                    function teardownMorphHook() {
                                        try {
                                            if (typeof offMorph === 'function') {
                                                offMorph();
                                                offMorph = null;
                                            }
                                        } catch (e) {
                                            /* ignore */
                                        }
                                    }
                                    function endHttp() {
                                        httpInFlight = Math.max(0, httpInFlight - 1);
                                        teardownMorphHook();
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

                                    const tRequest = performance.now();

                                    succeed(function (_fwd) {
                                        try {
                                            endHttp();
                                            const tSucceed = performance.now();
                                            lastWire = tSucceed - tRequest;
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
