/**
 * Söya smart singleton — ePOSDevice 印刷エンジン（公式サンプル準拠 + JIT 接続 + アイドル切断 + FIFO）。
 *
 * @see storage/ePOS_SDK_Sample_JavaScript/ReceiptDesigner/js/editor-print.js (setXmlString + send)
 * @see storage/ePOS_SDK_Sample_JavaScript/sample/PrinterObject.js (DEVICE_IN_USE リトライ)
 */

import { DEFAULT_TIMEOUT_MS } from './printer-interface.js';

/** @type {Array<QueuedJob>} */
let _queue = [];

/** @type {boolean} */
let _draining = false;

/** @type {object|null} */
let _ePosDev = null;

/** @type {object|null} */
let _printer = null;

/** @type {ReturnType<typeof setTimeout>|null} */
let _idleTimer = null;

/**
 * @typedef {object} QueuedJob
 * @property {string} jobKey
 * @property {string} xml
 * @property {Record<string, unknown>} opts
 * @property {number} printJobId
 * @property {(() => void)|undefined} onDispatched
 * @property {(value: object) => void} resolve
 */

/**
 * @param {string} xml
 * @returns {string}
 */
function stripEposPrintInner(xml) {
    const m = String(xml).match(/<epos-print[^>]*>([\s\S]*)<\/epos-print>/i);
    return m ? m[1].trim() : String(xml);
}

/**
 * @returns {null|{
 *   ip:string,
 *   port:number,
 *   deviceId:string,
 *   crypto:boolean,
 *   buffer:boolean,
 *   connectTimeoutMs:number,
 *   printTimeoutMs:number,
 *   idleDisconnectMs:number,
 *   deviceInUseRetryMax:number,
 *   deviceInUseRetryDelayMs:number,
 * }}
 */
function readMergedConfig() {
    const p = window.PosConfig;
    if (!p || !p.ip) {
        const f = window.posPrinterConfig;
        if (!f || !f.url) {
            return null;
        }
        try {
            const u = new URL(f.url);
            return {
                ip: u.hostname,
                port: parseInt(u.port || (u.protocol === 'https:' ? '443' : '80'), 10) || 8043,
                deviceId: 'local_printer',
                crypto: u.protocol === 'https:',
                buffer: false,
                connectTimeoutMs: 20000,
                printTimeoutMs: Math.max(1000, Number(f.timeoutMs ?? DEFAULT_TIMEOUT_MS)),
                idleDisconnectMs: 60000,
                deviceInUseRetryMax: 5,
                deviceInUseRetryDelayMs: 3000,
            };
        } catch {
            return null;
        }
    }

    return {
        ip: String(p.ip),
        port: parseInt(String(p.port), 10),
        deviceId: String(p.deviceId ?? p.device_id ?? 'local_printer'),
        crypto: p.crypto !== false,
        buffer: !!p.buffer,
        connectTimeoutMs: Math.max(3000, Number(p.connectTimeoutMs ?? p.connect_timeout_ms ?? 20000)),
        printTimeoutMs: Math.max(1000, Number(p.printTimeoutMs ?? p.print_timeout_ms ?? DEFAULT_TIMEOUT_MS)),
        idleDisconnectMs: Math.max(5000, Number(p.idleDisconnectMs ?? p.idle_disconnect_ms ?? 60000)),
        deviceInUseRetryMax: Math.max(1, Number(p.deviceInUseRetryMax ?? p.device_in_use_retry_max ?? 5)),
        deviceInUseRetryDelayMs: Math.max(500, Number(p.deviceInUseRetryDelayMs ?? p.device_in_use_retry_delay_ms ?? 3000)),
    };
}

function clearIdleTimer() {
    if (_idleTimer != null) {
        clearTimeout(_idleTimer);
        _idleTimer = null;
    }
}

function scheduleIdleDisconnect(cfg) {
    clearIdleTimer();
    const ms = cfg.idleDisconnectMs;
    _idleTimer = setTimeout(() => {
        hardDisconnect();
    }, ms);
}

function hardDisconnect() {
    clearIdleTimer();
    const dev = _ePosDev;
    const pr = _printer;
    _printer = null;
    if (dev && pr) {
        try {
            dev.deleteDevice(pr, () => {
                try {
                    dev.disconnect();
                } catch {
                    /* ignore */
                }
                _ePosDev = null;
            });
        } catch {
            try {
                dev.disconnect();
            } catch {
                /* ignore */
            }
            _ePosDev = null;
        }
    } else if (dev) {
        try {
            dev.disconnect();
        } catch {
            /* ignore */
        }
        _ePosDev = null;
    }
}

/**
 * @param {object} dev
 * @param {ReturnType<typeof readMergedConfig>} cfg
 */
function connectAsync(dev, cfg) {
    return new Promise((resolve) => {
        dev.connect(cfg.ip, cfg.port, (result) => {
            if (result === 'OK' || result === 'SSL_CONNECT_OK') {
                resolve({ ok: true });
            } else {
                resolve({ ok: false, code: String(result ?? 'CONNECT_FAIL'), message: 'ePOSDevice.connect failed' });
            }
        });
    });
}

/**
 * @param {object} dev
 * @param {ReturnType<typeof readMergedConfig>} cfg
 */
async function createPrinterWithRetry(dev, cfg) {
    const max = cfg.deviceInUseRetryMax;
    const delay = cfg.deviceInUseRetryDelayMs;

    for (let attempt = 1; attempt <= max; attempt++) {
        /** @type {{ printer: object|null, code: string }} */
        const result = await new Promise((resolve) => {
            dev.createDevice(
                cfg.deviceId,
                dev.DEVICE_TYPE_PRINTER,
                { crypto: cfg.crypto, buffer: cfg.buffer },
                (printer, code) => {
                    resolve({ printer, code: String(code ?? '') });
                },
            );
        });

        if (result.code === 'OK' && result.printer) {
            return result.printer;
        }

        if (result.code === 'DEVICE_IN_USE' && attempt < max) {
            await new Promise((r) => setTimeout(r, delay));
            continue;
        }

        return null;
    }

    return null;
}

/**
 * @param {object} printer
 * @param {string} xmlFull
 * @param {number} printTimeoutMs
 * @returns {Promise<{ ok: boolean, code?: string|null, message?: string|null, status?: string|null }>}
 */
function sendXmlAndWait(printer, xmlFull, printTimeoutMs) {
    return new Promise((resolve) => {
        let settled = false;
        const finish = (value) => {
            if (settled) return;
            settled = true;
            clearTimeout(timer);
            resolve(value);
        };

        const timer = setTimeout(() => {
            finish({ ok: false, code: 'EX_TIMEOUT', message: 'print response timeout' });
        }, printTimeoutMs + 3000);

        printer.timeout = printTimeoutMs;
        printer.onreceive = (res /* , _sq */) => {
            const ok = !!(res && res.success);
            const codeStr = res && res.code != null ? String(res.code) : '';
            const statusStr = res && res.status != null ? String(res.status) : '';
            finish(
                ok
                    ? { ok: true, code: codeStr || null, status: statusStr || null }
                    : {
                          ok: false,
                          code: codeStr || 'EPOS_FAIL',
                          message: statusStr ? `status=${statusStr}` : 'printer rejected',
                      },
            );
        };

        printer.onerror = (err /* , _sq */) => {
            const st = err && err.status != null ? String(err.status) : '';
            const txt = err && err.responseText ? String(err.responseText).slice(0, 400) : '';
            const sslLike =
                st === 'SSL' ||
                /ssl|certificate|tls/i.test(txt) ||
                /ssl|certificate/i.test(String(st));
            finish({
                ok: false,
                code: sslLike ? 'SSL_ERROR' : st ? `EPOS_${st}` : 'EPOS_ERR',
                message: txt || 'transport error',
            });
        };

        try {
            const inner = stripEposPrintInner(xmlFull);
            printer.setXmlString(inner);
            printer.send();
        } catch (e) {
            finish({
                ok: false,
                code: 'EPOS_THROW',
                message: e && e.message ? e.message : String(e),
            });
        }
    });
}

/**
 * @param {QueuedJob} job
 * @param {NonNullable<ReturnType<typeof readMergedConfig>>} cfg
 */
async function runOneJob(job, cfg) {
    const printTimeoutMs = Number(job.opts?.timeoutMs ?? cfg.printTimeoutMs ?? DEFAULT_TIMEOUT_MS);

    if (typeof job.onDispatched === 'function') {
        try {
            job.onDispatched();
        } catch {
            /* ignore */
        }
    }

    clearIdleTimer();

    if (!window.epson || typeof window.epson.ePOSDevice !== 'function') {
        return { ok: false, code: 'EPOS_SDK_MISSING', message: 'Epson ePOS SDK (ePOSDevice) not loaded' };
    }

    try {
        if (!_ePosDev) {
            _ePosDev = new window.epson.ePOSDevice();
            _ePosDev.CONNECT_TIMEOUT = cfg.connectTimeoutMs;
            _ePosDev.ondisconnect = () => {
                _printer = null;
                clearIdleTimer();
            };
        }

        const dev = _ePosDev;

        if (typeof dev.isConnected !== 'function' || !dev.isConnected()) {
            const conn = await connectAsync(dev, cfg);
            if (!conn.ok) {
                hardDisconnect();
                return conn;
            }
        }

        if (!_printer) {
            const p = await createPrinterWithRetry(dev, cfg);
            if (!p) {
                hardDisconnect();
                return { ok: false, code: 'DEVICE_OPEN_FAIL', message: 'createDevice failed after retries' };
            }
            _printer = p;
        }

        const sendRes = await sendXmlAndWait(_printer, job.xml, printTimeoutMs);

        if (sendRes.ok) {
            scheduleIdleDisconnect(cfg);
        } else {
            hardDisconnect();
        }

        return sendRes;
    } catch (e) {
        hardDisconnect();
        return {
            ok: false,
            code: 'EPOS_THROW',
            message: e && e.message ? e.message : String(e),
        };
    }
}

async function drainQueue() {
    if (_draining) return;
    _draining = true;

    try {
        while (_queue.length > 0) {
            const job = _queue.shift();
            if (!job) continue;

            const cfg = readMergedConfig();
            if (!cfg) {
                job.resolve({ ok: false, code: 'POS_CONFIG_MISSING', message: 'window.PosConfig / printer IP missing' });
                continue;
            }

            const res = await runOneJob(job, cfg);
            job.resolve(res);
        }
    } finally {
        _draining = false;
        if (_queue.length > 0) {
            void drainQueue();
        }
    }
}

/**
 * @param {object} payload
 * @param {string} payload.jobKey
 * @param {string} payload.xml
 * @param {Record<string, unknown>} [payload.opts]
 * @param {number} payload.printJobId
 * @param {(() => void)} [payload.onDispatched]
 * @returns {Promise<{ ok: boolean, code?: string|null, message?: string|null, status?: string|null }>}
 */
export function enqueueEposPrint(payload) {
    const { jobKey, xml, opts = {}, printJobId, onDispatched } = payload;

    return new Promise((resolve) => {
        _queue.push({
            jobKey,
            xml,
            opts,
            printJobId: Number(printJobId) || 0,
            onDispatched,
            resolve,
        });
        void drainQueue();
    });
}

/**
 * @returns {{ queueLength: number }}
 */
export function getEposPrintEngineStatus() {
    return { queueLength: _queue.length + (_draining ? 1 : 0) };
}
