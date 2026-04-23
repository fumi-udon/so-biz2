@if (config('app.speed_test'))
{{-- POS Livewire profiling panel (SPEED_TEST / app.speed_test). All logic self-contained; wire:ignore. --}}
<div
    id="pos-speed-panel-root"
    wire:ignore
    class="fi-pos-speed-panel flex w-full flex-none flex-col border-t border-zinc-600 bg-zinc-950 px-2 py-2 font-mono text-[9px] leading-snug text-zinc-100 shadow-[0_-4px_14px_rgba(0,0,0,0.25)] sm:px-3"
    role="status"
    aria-label="POS Livewire profiling"
>
    <div class="mb-1 flex flex-wrap items-center justify-between gap-1">
        <span class="text-[9px] font-bold uppercase tracking-wide text-amber-300">POS profile</span>
        <span id="pos-sp-health-strip" class="flex flex-wrap items-center gap-1 text-[8px]"></span>
    </div>
    <div
        id="pos-sp-phase-row"
        class="grid w-full max-w-full grid-cols-2 gap-x-2 gap-y-0.5 border-b border-zinc-800 pb-1.5 sm:grid-cols-4"
    >
        <div><span class="text-zinc-500">wire</span> <span id="pos-sp-total" class="font-semibold text-zinc-100">—</span></div>
        <div><span class="text-zinc-500">TTFBΔ</span> <span id="pos-sp-ttfb" class="font-semibold">—</span></div>
        <div><span class="text-zinc-500">dl~</span> <span id="pos-sp-dl" class="font-semibold">—</span></div>
        <div><span class="text-zinc-500">morph</span> <span id="pos-sp-morph" class="font-semibold">—</span></div>
    </div>
    <div class="mt-1 grid w-full max-w-full grid-cols-2 gap-x-2 gap-y-0.5 sm:grid-cols-4">
        <div><span class="text-zinc-500">mergeΣ</span> <span id="pos-sp-commit" class="text-zinc-200">—</span></div>
        <div><span class="text-zinc-500">HTTP/手</span> <span id="pos-sp-http-n" class="text-zinc-200">—</span></div>
        <div><span class="text-zinc-500">JSON</span> <span id="pos-sp-json-kb" class="text-zinc-200">—</span></div>
        <div><span class="text-zinc-500">htmlΣ</span> <span id="pos-sp-html-k" class="text-zinc-200">—</span></div>
    </div>
    <div class="mt-0.5 text-[8px] text-zinc-500">memoΣ <span id="pos-sp-memo-k" class="text-zinc-300">—</span></div>
    <div
        id="pos-sp-detail"
        class="mt-1.5 max-h-44 w-full max-w-full space-y-1 overflow-y-auto overscroll-contain border-t border-zinc-800 pt-1.5 text-[8px]"
    >
        <div>
            <span class="text-zinc-500">RT (last)</span>
            <span id="pos-sp-rt" class="block whitespace-pre-wrap break-all text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">wire split</span>
            <span id="pos-sp-wire-split" class="block break-words text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">HTTP batch</span>
            <span id="pos-sp-http-batch" class="block break-words text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">merge (comps)</span>
            <span id="pos-sp-commit-list" class="block break-words text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">morphed</span>
            <span id="pos-sp-morphed-list" class="block break-words text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">dispatches</span>
            <span id="pos-sp-dispatch-list" class="block break-words text-zinc-300">—</span>
        </div>
        <div>
            <span class="text-zinc-500">wire comps</span>
            <span id="pos-sp-comp-list" class="block break-words text-zinc-300">—</span>
        </div>
    </div>
    <div id="pos-sp-hint" class="mt-1 border-t border-zinc-800 pt-1 text-[7px] leading-tight text-zinc-600">
        TTFBΔ=ResourceTiming responseStart−requestStart（サーバ＋待ちの近似）。dl~=responseEnd−responseStart。mergeΣ=各 commit の respond→succeed（snapshot merge + processEffects 等のメイン同期コスト）。wire split: hook→request.respond（fetch 完了）→hook.succeed（本文＋parse＋handleSuccess）。緑/黄/赤は wire&lt;180 / 320 / 以上の目安。PHP 純粋時間は Server-Timing が無いと TTFB 内に埋没。
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

                    const TH = { wireOk: 180, wireWarn: 320, ttfbOk: 160, ttfbWarn: 280, jsonKbWarn: 80, htmlKWarn: 400 };

                    const $ = (id) => document.getElementById(id);

                    function fmt(ms) {
                        if (ms == null || Number.isNaN(ms)) {
                            return '—';
                        }
                        return Math.round(ms) + 'ms';
                    }

                    function badge(label, level) {
                        const c =
                            level === 'ok'
                                ? 'bg-emerald-700 text-white'
                                : level === 'warn'
                                  ? 'bg-amber-600 text-white'
                                  : 'bg-rose-700 text-white';
                        return '<span class="inline-flex items-center rounded px-1 py-px ' + c + '">' + label + '</span>';
                    }

                    function healthStrip(wireMs, ttfbMs, morphMs, jsonKb) {
                        try {
                            const el = $('pos-sp-health-strip');
                            if (!el) {
                                return;
                            }
                            const wL = wireMs == null || wireMs < TH.wireOk ? 'ok' : wireMs < TH.wireWarn ? 'warn' : 'bad';
                            const tL = ttfbMs == null || ttfbMs < TH.ttfbOk ? 'ok' : ttfbMs < TH.ttfbWarn ? 'warn' : 'bad';
                            const mL =
                                morphMs == null
                                    ? 'warn'
                                    : morphMs < 80
                                      ? 'ok'
                                      : morphMs < 200
                                        ? 'warn'
                                        : 'bad';
                            const jL = jsonKb == null || jsonKb < TH.jsonKbWarn ? 'ok' : jsonKb < 150 ? 'warn' : 'bad';
                            el.innerHTML =
                                badge('wire', wL) +
                                badge('TTFB', tL) +
                                badge('morph', mL) +
                                badge('JSON', jL);
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function findLastLivewireResourceEntry() {
                        try {
                            const entries = performance.getEntriesByType('resource');
                            for (let i = entries.length - 1; i >= 0; i--) {
                                const e = entries[i];
                                if (
                                    e.initiatorType === 'fetch' &&
                                    typeof e.name === 'string' &&
                                    (e.name.toLowerCase().indexOf('livewire') !== -1 || /\/livewire\//i.test(e.name))
                                ) {
                                    return e;
                                }
                            }
                        } catch (e) {
                            /* ignore */
                        }
                        return null;
                    }

                    function formatResourceTiming(e) {
                        if (!e) {
                            return '—';
                        }
                        try {
                            const parts = [];
                            const push = (k, a, b) => {
                                if (a > 0 && b > 0 && b >= a) {
                                    parts.push(k + ':' + Math.round(b - a) + 'ms');
                                }
                            };
                            push('dns', e.domainLookupStart, e.domainLookupEnd);
                            push('tcp', e.connectStart, e.connectEnd);
                            push('req→TTFB', e.requestStart, e.responseStart);
                            push('TTFB→end', e.responseStart, e.responseEnd);
                            push('total', e.startTime, e.responseEnd);
                            if (e.transferSize > 0) {
                                parts.push('xfer:' + e.transferSize + 'B');
                            }
                            return parts.join(' ');
                        } catch (err) {
                            return '—';
                        }
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

                    function analyzeJsonPayload(json) {
                        const out = {
                            jsonKb: null,
                            htmlChars: 0,
                            memoChars: 0,
                            names: [],
                        };
                        try {
                            if (json == null) {
                                return out;
                            }
                            const s = JSON.stringify(json);
                            out.jsonKb = s.length / 1024;
                            const comps = json.components;
                            if (!Array.isArray(comps)) {
                                return out;
                            }
                            for (let i = 0; i < comps.length; i++) {
                                const c = comps[i];
                                if (!c) {
                                    continue;
                                }
                                const eff = c.effects || {};
                                if (typeof eff.html === 'string') {
                                    out.htmlChars += eff.html.length;
                                } else if (Array.isArray(eff.html)) {
                                    for (let h = 0; h < eff.html.length; h++) {
                                        if (typeof eff.html[h] === 'string') {
                                            out.htmlChars += eff.html[h].length;
                                        }
                                    }
                                }
                                if (c.snapshot != null) {
                                    try {
                                        const snap = typeof c.snapshot === 'string' ? JSON.parse(c.snapshot) : c.snapshot;
                                        const memo = snap && snap.memo ? snap.memo : {};
                                        out.memoChars += JSON.stringify(memo).length;
                                        const nm = memo && memo.name != null ? String(memo.name) : '?';
                                        out.names.push(nm);
                                    } catch (e) {
                                        out.names.push('?');
                                    }
                                }
                            }
                        } catch (e) {
                            /* ignore */
                        }
                        return out;
                    }

                    function resetDetailPlaceholders() {
                        try {
                            [
                                'pos-sp-rt',
                                'pos-sp-wire-split',
                                'pos-sp-http-batch',
                                'pos-sp-commit-list',
                                'pos-sp-morphed-list',
                                'pos-sp-dispatch-list',
                                'pos-sp-comp-list',
                            ].forEach(function (id) {
                                const n = $(id);
                                if (n) {
                                    n.textContent = '…';
                                }
                            });
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function clearDetail() {
                        try {
                            [
                                'pos-sp-rt',
                                'pos-sp-wire-split',
                                'pos-sp-http-batch',
                                'pos-sp-commit-list',
                                'pos-sp-morphed-list',
                                'pos-sp-dispatch-list',
                                'pos-sp-comp-list',
                            ].forEach(function (id) {
                                const n = $(id);
                                if (n) {
                                    n.textContent = '—';
                                }
                            });
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    /** Gesture-scoped HTTP batch (POS dispatches these without touching this file). */
                    let gestureBatch = null;
                    const GESTURE_MS = 3200;

                    function touchGestureBatch() {
                        try {
                            gestureBatch = { t0: performance.now(), items: [] };
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    try {
                        window.addEventListener(
                            'show-local-skeleton',
                            function () {
                                try {
                                    touchGestureBatch();
                                } catch (e) {
                                    /* ignore */
                                }
                            },
                            true,
                        );
                        window.addEventListener('pos-tile-interaction-started', function () {
                            try {
                                touchGestureBatch();
                            } catch (e) {
                                /* ignore */
                            }
                        });
                    } catch (e) {
                        /* ignore */
                    }

                    let clientMergeStack = { active: false, mergeMs: 0, names: [] };

                    /** Outgoing Livewire pool: method hints + component memo names (best-effort). */
                    function sniffOutgoing(bodyStr) {
                        try {
                            const o = typeof bodyStr === 'string' ? JSON.parse(bodyStr) : bodyStr;
                            const comps = o && o.components;
                            if (!Array.isArray(comps)) {
                                return '';
                            }
                            const parts = [];
                            for (let i = 0; i < comps.length; i++) {
                                const c = comps[i];
                                if (!c) {
                                    continue;
                                }
                                const calls = c.calls || [];
                                const m = calls.length && calls[0].method ? String(calls[0].method) : '∅';
                                let name = '?';
                                try {
                                    if (typeof c.snapshot === 'string') {
                                        const s = JSON.parse(c.snapshot);
                                        if (s && s.memo && s.memo.name) {
                                            name = String(s.memo.name);
                                        }
                                    }
                                } catch (e) {
                                    /* ignore */
                                }
                                parts.push(name + ':' + m);
                            }
                            return parts.join(' + ');
                        } catch (e) {
                            return '';
                        }
                    }

                    const inflightByRid = {};

                    let httpInFlight = 0;
                    let lastWire = null;
                    let lastTtfb = null;
                    let lastDl = null;
                    let lastMorph = null;
                    let lastMergeSum = null;
                    let activeRound = null;
                    let requestSeq = 0;

                    function paintCore() {
                        try {
                            $('pos-sp-total').textContent = fmt(lastWire);
                            $('pos-sp-ttfb').textContent = fmt(lastTtfb);
                            $('pos-sp-dl').textContent = fmt(lastDl);
                            $('pos-sp-morph').textContent = fmt(lastMorph);
                            $('pos-sp-commit').textContent = fmt(lastMergeSum);
                            const jk = $('pos-sp-json-kb');
                            const hk = $('pos-sp-html-k');
                            const mk = $('pos-sp-memo-k');
                            if (jk && window.__posSpeedLastPayload) {
                                jk.textContent = window.__posSpeedLastPayload.jsonKb.toFixed(2) + 'KB';
                                hk.textContent = Math.round(window.__posSpeedLastPayload.htmlChars / 1000) + 'k chars';
                                mk.textContent = Math.round(window.__posSpeedLastPayload.memoChars / 1000) + 'k chars';
                            } else {
                                if (jk) {
                                    jk.textContent = '—';
                                }
                                if (hk) {
                                    hk.textContent = '—';
                                }
                                if (mk) {
                                    mk.textContent = '—';
                                }
                            }
                            healthStrip(lastWire, lastTtfb, lastMorph, window.__posSpeedLastPayload ? window.__posSpeedLastPayload.jsonKb : null);
                        } catch (e) {
                            /* ignore */
                        }
                    }

                    function scheduleMorphPaintDone(tFirstMorph) {
                        try {
                            if (tFirstMorph == null) {
                                lastMorph = null;
                                paintCore();
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
                                                        paintCore();
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

                            /** Per-commit client work: respond() → succeed() inside handleResponse (merge snapshot + processEffects, etc.). */
                            lw.hook('commit', function (ref) {
                                let tAfterRespond = null;
                                try {
                                    const resp = ref.respond;
                                    const succ = ref.succeed;
                                    if (typeof resp === 'function') {
                                        resp(function () {
                                            try {
                                                if (clientMergeStack.active) {
                                                    tAfterRespond = performance.now();
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    }
                                    if (typeof succ === 'function') {
                                        succ(function () {
                                            try {
                                                if (clientMergeStack.active && tAfterRespond != null) {
                                                    clientMergeStack.mergeMs += performance.now() - tAfterRespond;
                                                    tAfterRespond = null;
                                                }
                                                const comp = ref.component;
                                                if (clientMergeStack.active && comp && comp.name) {
                                                    clientMergeStack.names.push(String(comp.name));
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    }
                                } catch (e) {
                                    /* ignore */
                                }
                            });

                            lw.hook('request', function (_ref) {
                                try {
                                    const succeed = _ref.succeed;
                                    const fail = _ref.fail;
                                    const respond = _ref.respond;
                                    if (typeof succeed !== 'function') {
                                        return;
                                    }

                                    resetDetailPlaceholders();

                                    const rid = ++requestSeq;
                                    const round = {
                                        rid: rid,
                                        morphed: new Map(),
                                        tRequest: performance.now(),
                                        tFetchDone: null,
                                    };
                                    activeRound = round;

                                    clientMergeStack = { active: true, mergeMs: 0, names: [] };

                                    try {
                                        const hint = sniffOutgoing(_ref.payload);
                                        const method = (_ref.options && _ref.options.method) || 'POST';
                                        inflightByRid[rid] = { method: method, hint: hint, t: round.tRequest };
                                        const ids = Object.keys(inflightByRid);
                                        round.concurrentSummary =
                                            ids.length > 1
                                                ? ids
                                                      .map(function (k) {
                                                          const v = inflightByRid[k];
                                                          return '#' + k + ' ' + v.method + ' ' + (v.hint || '—');
                                                      })
                                                      .join(' │ ')
                                                : '';
                                    } catch (e) {
                                        round.concurrentSummary = '';
                                    }

                                    if (gestureBatch && performance.now() - gestureBatch.t0 < GESTURE_MS) {
                                        gestureBatch.items.push({
                                            rid: rid,
                                            t: round.tRequest,
                                            hint: inflightByRid[rid] ? inflightByRid[rid].hint : '',
                                        });
                                    }

                                    if (typeof respond === 'function') {
                                        respond(function () {
                                            try {
                                                round.tFetchDone = performance.now();
                                            } catch (e) {
                                                /* ignore */
                                            }
                                        });
                                    }

                                    httpInFlight++;
                                    const morphSlot = { t0: null };
                                    let morphPairStart = null;
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
                                                const r = activeRound;
                                                if (r && r.rid === rid) {
                                                    morphPairStart = performance.now();
                                                    if (httpInFlight === 1 && morphSlot.t0 === null) {
                                                        morphSlot.t0 = morphPairStart;
                                                    }
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
                                                if (!r || !detail || r.rid !== rid) {
                                                    return;
                                                }
                                                if (morphPairStart != null) {
                                                    r.morphSyncMs = (r.morphSyncMs || 0) + (performance.now() - morphPairStart);
                                                    morphPairStart = null;
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

                                    succeed(function (fwd) {
                                        try {
                                            endHttp();
                                            const tDone = performance.now();
                                            lastWire = tDone - round.tRequest;
                                            lastMergeSum = clientMergeStack.mergeMs;
                                            clientMergeStack.active = false;
                                            try {
                                                delete inflightByRid[rid];
                                            } catch (eDel) {
                                                /* ignore */
                                            }

                                            const rt = findLastLivewireResourceEntry();
                                            let ttfb = null;
                                            let dl = null;
                                            try {
                                                if (rt && rt.requestStart > 0 && rt.responseStart > 0) {
                                                    ttfb = rt.responseStart - rt.requestStart;
                                                }
                                                if (rt && rt.responseStart > 0 && rt.responseEnd > 0) {
                                                    dl = rt.responseEnd - rt.responseStart;
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                            lastTtfb = ttfb;
                                            lastDl = dl;

                                            const j = fwd && fwd.json != null ? fwd.json : null;
                                            const analysis = analyzeJsonPayload(j);
                                            window.__posSpeedLastPayload = analysis;

                                            const morphedMap = round.morphed || new Map();
                                            const disp = extractDispatchNamesFromJson(j);

                                            try {
                                                $('pos-sp-rt').textContent = formatResourceTiming(rt);
                                            } catch (e) {
                                                /* ignore */
                                            }
                                            try {
                                                const gb = gestureBatch;
                                                let batchLine = '—';
                                                if (gb && gb.items && gb.items.length > 0) {
                                                    const recent = gb.items.filter(function (it) {
                                                        return performance.now() - it.t < GESTURE_MS;
                                                    });
                                                    batchLine = recent.length + ' req';
                                                }
                                                $('pos-sp-http-n').textContent = batchLine;
                                                const bEl = $('pos-sp-http-batch');
                                                if (bEl) {
                                                    const parts = [];
                                                    if (round.concurrentSummary) {
                                                        parts.push('同時: ' + round.concurrentSummary);
                                                    }
                                                    if (gestureBatch && gestureBatch.items && gestureBatch.items.length) {
                                                        parts.push(
                                                            gestureBatch.items
                                                                .map(function (x) {
                                                                    return '#' + x.rid + (x.hint ? ' ' + x.hint : '');
                                                                })
                                                                .join(' · '),
                                                        );
                                                    }
                                                    bEl.textContent = parts.length ? parts.join(' | ') : '—';
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }

                                            try {
                                                const wEl = $('pos-sp-wire-split');
                                                if (wEl) {
                                                    const a = round.tFetchDone != null ? Math.round(round.tFetchDone - round.tRequest) : null;
                                                    const b =
                                                        round.tFetchDone != null ? Math.round(tDone - round.tFetchDone) : null;
                                                    wEl.textContent =
                                                        (a != null ? 'hook→fetch ' + a + 'ms' : 'hook→fetch —') +
                                                        ' · ' +
                                                        (b != null ? 'fetch→done ' + b + 'ms' : 'fetch→done —');
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }

                                            try {
                                                const cel = $('pos-sp-commit-list');
                                                if (cel) {
                                                    cel.textContent = clientMergeStack.names.length
                                                        ? clientMergeStack.names.join(', ')
                                                        : '（なし）';
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                            try {
                                                const mel = $('pos-sp-morphed-list');
                                                if (mel) {
                                                    const names = morphedMap.size ? Array.from(morphedMap.values()).join(', ') : '（なし）';
                                                    const ms = round.morphSyncMs != null ? Math.round(round.morphSyncMs) : null;
                                                    mel.textContent = names + (ms != null ? ' · morphΣ ' + ms + 'ms' : '');
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                            try {
                                                const del = $('pos-sp-dispatch-list');
                                                if (del) {
                                                    del.textContent = disp.length ? disp.join(', ') : '（なし）';
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }
                                            try {
                                                const wel = $('pos-sp-comp-list');
                                                if (wel) {
                                                    wel.textContent = analysis.names.length ? analysis.names.join(', ') : '（なし）';
                                                }
                                            } catch (e) {
                                                /* ignore */
                                            }

                                            activeRound = null;
                                            clientMergeStack = { active: false, mergeMs: 0, names: [] };

                                            queueMicrotask(function () {
                                                try {
                                                    paintCore();
                                                } catch (e) {
                                                    /* ignore */
                                                }
                                            });
                                            paintCore();
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
                                                try {
                                                    delete inflightByRid[rid];
                                                } catch (e2) {
                                                    /* ignore */
                                                }
                                                clientMergeStack = { active: false, mergeMs: 0, names: [] };
                                                clearDetail();
                                                paintCore();
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
@endif
