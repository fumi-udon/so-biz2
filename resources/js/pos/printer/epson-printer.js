import { DEFAULT_TIMEOUT_MS, PrinterInterface } from './printer-interface.js';

/**
 * EpsonHttpPrinter — thin wrapper around window.epson.ePOSPrint (the HTTP
 * transport in public/js/epos-2.27.0.js). The SDK's own timeout default
 * (~5min) is intolerable at the POS counter, so we wrap each send in a
 * 10s Promise.race.
 *
 * The printer URL comes from the caller (server-rendered config) so a
 * shop can switch the TM-m30II address without a JS build.
 */
export class EpsonHttpPrinter extends PrinterInterface {
    constructor({ url, timeoutMs = DEFAULT_TIMEOUT_MS } = {}) {
        super();
        if (!url) throw new Error('EpsonHttpPrinter: url is required');
        this._url = url;
        this._timeoutMs = timeoutMs;
        this._inflight = new Map();
    }

    async send(jobKey, xmlPayload, opts = {}) {
        if (!window.epson || !window.epson.ePOSPrint) {
            return { ok: false, code: 'EPOS_SDK_MISSING', message: 'Epson ePOS SDK not loaded' };
        }
        if (this._inflight.has(jobKey)) {
            return this._inflight.get(jobKey);
        }

        const timeoutMs = Number(opts.timeoutMs ?? this._timeoutMs);
        const promise = new Promise((resolve) => {
            let settled = false;
            const finish = (value) => {
                if (settled) return;
                settled = true;
                resolve(value);
            };

            const timer = setTimeout(() => {
                finish({ ok: false, code: 'EPOS_TIMEOUT', message: `printer did not respond in ${timeoutMs}ms` });
            }, timeoutMs);

            try {
                // eslint-disable-next-line new-cap
                const ePosPrint = new window.epson.ePOSPrint(this._url);

                ePosPrint.timeout = timeoutMs;
                ePosPrint.onreceive = (res) => {
                    clearTimeout(timer);
                    if (res && res.success) {
                        finish({ ok: true, code: res.code ?? null, status: res.status ?? null });
                    } else {
                        finish({
                            ok: false,
                            code: (res && res.code) ? String(res.code) : 'EPOS_FAIL',
                            message: (res && res.status) ? `status=${res.status}` : 'printer rejected',
                        });
                    }
                };
                ePosPrint.onerror = (err) => {
                    clearTimeout(timer);
                    finish({
                        ok: false,
                        code: (err && err.status) ? `EPOS_${err.status}` : 'EPOS_ERR',
                        message: (err && err.responseText) ? String(err.responseText).slice(0, 500) : 'transport error',
                    });
                };

                ePosPrint.send(xmlPayload);
            } catch (e) {
                clearTimeout(timer);
                finish({ ok: false, code: 'EPOS_THROW', message: e && e.message ? e.message : String(e) });
            }
        });

        this._inflight.set(jobKey, promise);
        try {
            return await promise;
        } finally {
            this._inflight.delete(jobKey);
        }
    }
}
