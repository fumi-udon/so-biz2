@if (config('app.speed_test'))
<div
    id="pos-speed-probe-root"
    wire:ignore
    class="flex w-full flex-none flex-col border-t border-indigo-700 bg-slate-950 px-2 py-2 font-mono text-[9px] leading-snug text-slate-100 sm:px-3"
    role="status"
    aria-label="POS speed probe events"
>
    <div class="mb-1 flex items-center justify-between gap-2">
        <span class="text-[9px] font-bold uppercase tracking-wide text-indigo-300">POS probe</span>
        <button
            id="pos-speed-probe-clear"
            type="button"
            class="rounded border border-slate-600 bg-slate-800 px-1.5 py-0.5 text-[8px] font-semibold text-slate-100 hover:bg-slate-700"
        >clear</button>
    </div>
    <div class="grid grid-cols-3 gap-1 text-[8px]">
        <div><span class="text-slate-500">events</span> <span id="pos-speed-probe-count" class="text-slate-200">0</span></div>
        <div><span class="text-slate-500">last tag</span> <span id="pos-speed-probe-tag" class="text-slate-200">—</span></div>
        <div><span class="text-slate-500">last at</span> <span id="pos-speed-probe-at" class="text-slate-200">—</span></div>
    </div>
    <div id="pos-speed-probe-log" class="mt-1.5 max-h-40 overflow-y-auto overscroll-contain border-t border-slate-800 pt-1.5 text-[8px] text-slate-200">
        <div class="text-slate-500">waiting for pos-speed-probe events...</div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                try {
                    const root = document.getElementById('pos-speed-probe-root');
                    if (!root) {
                        return;
                    }
                    const countEl = document.getElementById('pos-speed-probe-count');
                    const tagEl = document.getElementById('pos-speed-probe-tag');
                    const atEl = document.getElementById('pos-speed-probe-at');
                    const logEl = document.getElementById('pos-speed-probe-log');
                    const clearBtn = document.getElementById('pos-speed-probe-clear');
                    if (!countEl || !tagEl || !atEl || !logEl || !clearBtn) {
                        return;
                    }

                    const traces = [];
                    const maxTraces = 80;

                    const render = function () {
                        countEl.textContent = String(traces.length);
                        const last = traces.length > 0 ? traces[traces.length - 1] : null;
                        tagEl.textContent = last ? String(last.tag || 'unknown') : '—';
                        atEl.textContent = last ? String(last.at || '—') : '—';
                        if (!last) {
                            logEl.innerHTML = '<div class="text-slate-500">waiting for pos-speed-probe events...</div>';
                            return;
                        }
                        const html = traces
                            .slice(-40)
                            .reverse()
                            .map(function (row) {
                                const detail = row.detail || {};
                                const json = JSON.stringify(detail);
                                return (
                                    '<div class="mb-1 rounded border border-slate-800 bg-slate-900/80 px-1.5 py-1">' +
                                    '<div class="text-indigo-300">[' + row.at + '] ' + String(row.tag || 'unknown') + '</div>' +
                                    '<div class="whitespace-pre-wrap break-all text-slate-300">' + json + '</div>' +
                                    '</div>'
                                );
                            })
                            .join('');
                        logEl.innerHTML = html;
                    };

                    clearBtn.addEventListener('click', function () {
                        traces.length = 0;
                        render();
                    });

                    window.addEventListener('pos-speed-probe', function (event) {
                        const d = event && event.detail ? event.detail : {};
                        const ts = typeof d.ts === 'number' ? d.ts : Date.now();
                        const at = new Date(ts).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                        traces.push({
                            at: at,
                            tag: d.tag || 'unknown',
                            detail: d.detail && typeof d.detail === 'object' ? d.detail : {},
                        });
                        while (traces.length > maxTraces) {
                            traces.shift();
                        }
                        render();
                    });
                } catch (e) {
                    // ignore probe errors
                }
            })();
        </script>
    @endpush
@endonce
@endif
