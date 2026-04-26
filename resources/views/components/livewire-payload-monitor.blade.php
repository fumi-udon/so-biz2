{{--
  TEMP: Livewire /livewire/update レスポンス実測（SPEED_TEST のみ）。
  削除手順:
  1) このファイルを削除: resources/views/components/livewire-payload-monitor.blade.php
  2) resources/views/livewire/pos/table-dashboard.blade.php から
     <x-livewire-payload-monitor /> の1行を削除（<x-pos-speed-panel /> のみ残す）
--}}
@if (config('app.speed_test'))
<div
    id="livewire-payload-monitor-root"
    wire:ignore
    class="fi-lw-payload-monitor flex w-full flex-none flex-col border-t border-violet-900/80 bg-violet-950 px-2 py-1.5 font-mono text-[9px] leading-snug text-violet-100 shadow-[0_-2px_10px_rgba(0,0,0,0.2)] sm:px-3"
    role="status"
    aria-label="Livewire payload size"
>
    <div class="mb-0.5 text-[8px] font-bold uppercase tracking-wide text-violet-300">Livewire payload (last update)</div>
    <div id="lw-pm-main" class="break-words text-violet-50">[PAYLOAD] —</div>
    <div id="lw-pm-hist" class="mt-0.5 text-[8px] text-violet-300/90">履歴: —</div>
</div>

@once
    @push('scripts')
        <script>
            (function () {
                try {
                    if (!document.getElementById('livewire-payload-monitor-root')) {
                        return;
                    }
                    if (window.__livewirePayloadMonitorInstalled) {
                        return;
                    }
                    window.__livewirePayloadMonitorInstalled = true;

                    const $main = () => document.getElementById('lw-pm-main');
                    const $hist = () => document.getElementById('lw-pm-hist');
                    const history = [];

                    function fmtKb(n) {
                        if (n == null || Number.isNaN(n) || n < 0) {
                            return '—';
                        }
                        if (n < 1024) {
                            return n + ' B';
                        }
                        return (n / 1024).toFixed(1) + ' kB';
                    }

                    function parsePathname(url) {
                        try {
                            return new URL(url, window.location.origin).pathname;
                        } catch (e) {
                            return String(url || '');
                        }
                    }

                    function extractNameFromSnapshot(snapshotRaw) {
                        try {
                            const o = typeof snapshotRaw === 'string' ? JSON.parse(snapshotRaw) : snapshotRaw;
                            if (o && o.memo && o.memo.name) {
                                return String(o.memo.name);
                            }
                        } catch (e) {
                            /* ignore */
                        }
                        return '?';
                    }

                    function analyzePayload(text) {
                        const o = JSON.parse(text);
                        const comps = Array.isArray(o.components) ? o.components : [];
                        let htmlChars = 0;
                        let largestName = '—';
                        let largestLen = -1;
                        for (let i = 0; i < comps.length; i++) {
                            const c = comps[i];
                            const nm = extractNameFromSnapshot(c.snapshot);
                            const h = c.effects && typeof c.effects.html === 'string' ? c.effects.html : '';
                            const len = h.length;
                            htmlChars += len;
                            if (len > largestLen) {
                                largestLen = len;
                                largestName = nm;
                            }
                        }
                        return {
                            compsCount: comps.length,
                            htmlChars: htmlChars,
                            largest: largestName,
                            decodedUtf8: new TextEncoder().encode(text).length,
                        };
                    }

                    function pickResourceTiming() {
                        try {
                            const entries = performance.getEntriesByType('resource');
                            let best = null;
                            for (let i = 0; i < entries.length; i++) {
                                const e = entries[i];
                                if (!e.name) {
                                    continue;
                                }
                                const p = parsePathname(e.name);
                                if (!p.includes('livewire/update')) {
                                    continue;
                                }
                                if (!best || e.responseEnd >= best.responseEnd) {
                                    best = e;
                                }
                            }
                            return best;
                        } catch (e) {
                            return null;
                        }
                    }

                    function updateUi(encBytes, decodedBytes, stats, recordHistory) {
                        const main = $main();
                        const hist = $hist();
                        if (!main || !hist) {
                            return;
                        }
                        const line =
                            '[PAYLOAD] size: ' +
                            fmtKb(encBytes) +
                            ' / decoded: ' +
                            fmtKb(decodedBytes) +
                            ' | comps: ' +
                            stats.compsCount +
                            ' | html: ' +
                            stats.htmlChars.toLocaleString() +
                            ' chars | largest: ' +
                            stats.largest;
                        main.textContent = line;

                        if (
                            recordHistory &&
                            encBytes != null &&
                            !Number.isNaN(encBytes) &&
                            encBytes > 0
                        ) {
                            history.push(encBytes);
                            while (history.length > 5) {
                                history.shift();
                            }
                        }
                        hist.textContent =
                            history.length > 0
                                ? '履歴: ' +
                                  history
                                      .map(function (b) {
                                          return (b / 1024).toFixed(1);
                                      })
                                      .join(' / ') +
                                  ' kB'
                                : '履歴: —';
                    }

                    const nativeFetch = window.fetch.bind(window);
                    window.fetch = function (input, init) {
                        const reqUrl = typeof input === 'string' ? input : input && input.url ? input.url : '';
                        return nativeFetch(input, init).then(function (response) {
                            try {
                                const pathname = parsePathname(reqUrl);
                                if (!pathname.includes('livewire/update')) {
                                    return response;
                                }
                                const clone = response.clone();
                                queueMicrotask(function () {
                                    clone
                                        .text()
                                        .then(function (text) {
                                            let stats;
                                            try {
                                                stats = analyzePayload(text);
                                            } catch (e) {
                                                stats = {
                                                    compsCount: 0,
                                                    htmlChars: 0,
                                                    largest: '(parse error)',
                                                    decodedUtf8: new TextEncoder().encode(text).length,
                                                };
                                            }
                                            const apply = function (recordHistory) {
                                                const rt = pickResourceTiming();
                                                let enc = rt && rt.encodedBodySize > 0 ? rt.encodedBodySize : null;
                                                let decPerf = rt && rt.decodedBodySize > 0 ? rt.decodedBodySize : null;
                                                if (enc == null && rt && rt.transferSize > 0) {
                                                    enc = rt.transferSize;
                                                }
                                                const decoded = decPerf != null && decPerf > 0 ? decPerf : stats.decodedUtf8;
                                                updateUi(enc, decoded, stats, recordHistory);
                                            };
                                            apply(false);
                                            setTimeout(function () {
                                                apply(true);
                                            }, 48);
                                        })
                                        .catch(function () {
                                            /* ignore clone read errors */
                                        });
                                });
                            } catch (e) {
                                /* ignore */
                            }
                            return response;
                        });
                    };
                } catch (e) {
                    /* ignore */
                }
            })();
        </script>
    @endpush
@endonce
@endif
