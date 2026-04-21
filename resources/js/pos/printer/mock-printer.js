import { DEFAULT_TIMEOUT_MS, PrinterInterface } from './printer-interface.js';

/**
 * MockPrinter — renders the XML into a DOM preview pane and resolves after
 * a deterministic short delay. Used whenever the real hardware is not
 * reachable (dev / tests / pre-router deployment). Behaviour is driven by
 * a few URL-level opt-ins so QA can simulate failures without changing
 * the driver factory:
 *
 *   ?mockPrinter=fail   → resolves with {ok:false, code:'MOCK_FAIL'}
 *   ?mockPrinter=slow   → waits slightly longer to exercise UI spinners
 *
 * The preview pane is an off-by-default absolutely-positioned panel that
 * appears on first send; repeated sends push new entries on top.
 */
export class MockPrinter extends PrinterInterface {
    constructor({ container = null, simulateLatencyMs = 250 } = {}) {
        super();
        this._container = container;
        this._simulateLatencyMs = simulateLatencyMs;
        this._inflight = new Map();
    }

    async send(jobKey, xmlPayload, opts = {}) {
        if (typeof jobKey !== 'string' || jobKey === '') {
            return { ok: false, code: 'MOCK_BAD_JOB_KEY', message: 'jobKey is required' };
        }
        if (this._inflight.has(jobKey)) {
            return this._inflight.get(jobKey);
        }

        const timeoutMs = Number(opts.timeoutMs ?? DEFAULT_TIMEOUT_MS);
        const mode = this._readMockMode();
        const latency = mode === 'slow' ? Math.min(timeoutMs - 100, 3_000) : this._simulateLatencyMs;

        const promise = new Promise((resolve) => {
            const start = Date.now();
            const timer = setTimeout(() => {
                if (mode === 'fail') {
                    this._renderEntry(jobKey, xmlPayload, { ok: false, code: 'MOCK_FAIL', message: 'simulated failure' });
                    resolve({ ok: false, code: 'MOCK_FAIL', message: 'simulated failure' });
                    return;
                }
                this._renderEntry(jobKey, xmlPayload, { ok: true });
                resolve({ ok: true, elapsedMs: Date.now() - start });
            }, latency);

            // Hard timeout guard so a runaway mock can't wedge UI either.
            setTimeout(() => {
                clearTimeout(timer);
                resolve({ ok: false, code: 'MOCK_TIMEOUT', message: 'mock printer timeout' });
            }, timeoutMs);
        });

        this._inflight.set(jobKey, promise);
        try {
            return await promise;
        } finally {
            this._inflight.delete(jobKey);
        }
    }

    _readMockMode() {
        try {
            const url = new URL(window.location.href);
            return (url.searchParams.get('mockPrinter') ?? '').toLowerCase();
        } catch {
            return '';
        }
    }

    _renderEntry(jobKey, xml, result) {
        const target = this._container ?? this._ensureDomPane();
        if (!target) return;

        // eslint-disable-next-line no-console
        console.groupCollapsed(`[MockPrinter] ${result.ok ? '✅' : '❌'} ${jobKey.slice(0, 10)}`);
        // eslint-disable-next-line no-console
        console.log(xml);
        // eslint-disable-next-line no-console
        console.groupEnd();

        const entry = document.createElement('pre');
        entry.className = 'pos-mock-printer__entry';
        entry.setAttribute('data-job-key', jobKey);
        entry.setAttribute('data-ok', String(result.ok));
        entry.style.cssText = 'margin:4px;padding:6px;border:1px solid #ddd;background:#fafafa;font-size:11px;white-space:pre-wrap;word-break:break-all;';
        entry.textContent = `[${new Date().toISOString()}] ${result.ok ? 'OK' : `ERR ${result.code}`}\n${this._stripXmlTags(xml)}`;
        target.prepend(entry);
    }

    _stripXmlTags(xml) {
        return xml
            .replace(/<\?xml[^>]*>/g, '')
            .replace(/<[^>]+>/g, '')
            .replace(/&#10;/g, '\n')
            .replace(/&#233;/g, 'é')
            .trim();
    }

    _ensureDomPane() {
        if (typeof document === 'undefined') return null;
        let pane = document.getElementById('pos-mock-printer-pane');
        if (pane) return pane;
        pane = document.createElement('div');
        pane.id = 'pos-mock-printer-pane';
        pane.style.cssText = 'position:fixed;bottom:8px;right:8px;width:280px;max-height:42vh;overflow:auto;background:#fff;border:1px solid #ccc;box-shadow:0 6px 16px rgba(0,0,0,.2);z-index:99999;font-family:monospace;';
        const title = document.createElement('div');
        title.textContent = 'Mock Printer Preview';
        title.style.cssText = 'padding:6px;background:#222;color:#fff;font-size:11px;font-weight:bold;';
        pane.appendChild(title);
        document.body.appendChild(pane);
        return pane;
    }
}
