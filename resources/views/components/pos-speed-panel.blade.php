{{-- POS Livewire timing panel: only rendered when SPEED_TEST / app.speed_test is on --}}
<div
    id="pos-speed-panel-root"
    wire:ignore
    class="pointer-events-none fixed bottom-2 right-2 z-[9999] max-w-[min(100vw-1rem,22rem)] select-none rounded-md border border-gray-600 bg-black/95 px-2 py-1.5 font-mono text-[10px] leading-snug text-gray-100 shadow-lg"
    style="opacity: 1"
    aria-hidden="true"
>
    <div class="mb-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-300">POS speed</div>
    <div id="pos-speed-panel-rows" class="space-y-0.5 text-gray-200">
        <div>wire: <span id="pos-sp-total">—</span></div>
        <div>srvΔ: <span id="pos-sp-back">—</span></div>
        <div>net~: <span id="pos-sp-net">—</span></div>
        <div>morph: <span id="pos-sp-morph">—</span></div>
    </div>
    <div id="pos-speed-panel-hint" class="mt-1 border-t border-gray-700 pt-0.5 text-[8px] text-gray-500">
        srvΔ = responseStart−requestStart (TTFB wait). net~ = wire − srvΔ. morph = first morph → 2×rAF.
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

                    let cycleFirstMorph = null;
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
                                    if (typeof succeed !== 'function') {
                                        return;
                                    }
                                    const tRequest = performance.now();
                                    cycleFirstMorph = null;

                                    succeed(function (_fwd) {
                                        try {
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
                                            scheduleMorphPaintDone(cycleFirstMorph);
                                        } catch (e) {
                                            /* ignore */
                                        }
                                    });
                                } catch (e) {
                                    /* ignore */
                                }
                            });

                            lw.hook('morph', function () {
                                try {
                                    if (cycleFirstMorph == null) {
                                        cycleFirstMorph = performance.now();
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
