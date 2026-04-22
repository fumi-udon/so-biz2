/**
 * Söya smart singleton — ePOSDevice 印刷エンジン（公式サンプル準拠 + JIT 接続 + アイドル切断 + FIFO）。
 *
 * 現場向け: 切断完了前に次ジョブが走ると ERROR_TIMEOUT / 不整合が起きやすいため、
 * deleteDevice / disconnect は必ず Promise で直列化する。
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

/** @type {Promise<void>|null} */
let _pendingDisconnect = null;

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
            const portFromUrl = u.port ? parseInt(u.port, 10) : 8043;
            return {
                ip: u.hostname,
                port: Number.isFinite(portFromUrl) && portFromUrl > 0 ? portFromUrl : 8043,
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

/**
 * アイドル時にポート解放（他端末・メンテ用）。非同期で完全切断まで待つ。
 */
function scheduleIdleDisconnect(cfg) {
    clearIdleTimer();
    const ms = cfg.idleDisconnectMs;
    _idleTimer = setTimeout(() => {
        void disconnectNow();
    }, ms);
}

/**
 * 進行中の切断があれば完了まで待つ（次ジョブとの競合防止）。
 */
async function waitForPendingDisconnect() {
    if (_pendingDisconnect) {
        try {
            await _pendingDisconnect;
        } catch {
            /* ignore */
        }
    }
}

/**
 * deleteDevice → disconnect まで含めた完全切断。Promise で直列化。
 */
function disconnectNow() {
    if (_pendingDisconnect) {
        return _pendingDisconnect;
    }

    clearIdleTimer();

    const dev = _ePosDev;
    const pr = _printer;
    _printer = null;

    if (!dev) {
        _ePosDev = null;
        return Promise.resolve();
    }

    _pendingDisconnect = new Promise((resolve) => {
        let settled = false;
        const finish = () => {
            if (settled) {
                return;
            }
            settled = true;
            _ePosDev = null;
            _pendingDisconnect = null;
            resolve();
        };

        const safety = setTimeout(finish, 12_000);

        try {
            if (pr) {
                dev.deleteDevice(pr, () => {
                    try {
                        dev.disconnect();
                    } catch {
                        /* ignore */
                    }
                    clearTimeout(safety);
                    finish();
                });
            } else {
                try {
                    dev.disconnect();
                } catch {
                    /* ignore */
                }
                clearTimeout(safety);
                finish();
            }
        } catch {
            try {
                dev.disconnect();
            } catch {
                /* ignore */
            }
            clearTimeout(safety);
            finish();
        }
    });

    return _pendingDisconnect;
}

/**
 * @param {number} ms
 */
function sleep(ms) {
    return new Promise((r) => setTimeout(r, ms));
}

/**
 * @param {string} code
 */
function isConnectRetryable(code) {
    const c = String(code ?? '').toUpperCase();
    return (
        c === 'ERROR_TIMEOUT' ||
        c.includes('TIMEOUT') ||
        c === 'DEVICE_IN_USE' ||
        c === 'SSL_CONNECT_FAIL' ||
        c === 'ERROR_SYSTEM'
    );
}

/**
 * @param {string|null|undefined} code
 * @param {string|null|undefined} message
 */
function isSendRetryable(code, message) {
    const c = String(code ?? '').toUpperCase();
    const m = String(message ?? '');
    if (c === 'EX_TIMEOUT' || c === 'ERROR_TIMEOUT' || c.includes('TIMEOUT')) {
        return true;
    }
    if (c === 'EPOS_FAIL' || c === 'EPOS_ERR' || c === '') {
        return /timeout|time.?out|応答|response/i.test(m);
    }
    return false;
}

/**
 * @param {object} dev
 * @param {NonNullable<ReturnType<typeof readMergedConfig>>} cfg
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
 * @param {NonNullable<ReturnType<typeof readMergedConfig>>} cfg
 */
async function connectWithRetry(dev, cfg) {
    const max = 4;
    for (let attempt = 1; attempt <= max; attempt++) {
        const conn = await connectAsync(dev, cfg);
        if (conn.ok) {
            return conn;
        }
        const code = String(conn.code ?? '');
        if (attempt < max && isConnectRetryable(code)) {
            await disconnectNow();
            await sleep(600 * attempt);
            continue;
        }
        return conn;
    }
    return { ok: false, code: 'CONNECT_FAIL', message: 'connect retries exhausted' };
}

/**
 * @param {object} dev
 * @param {NonNullable<ReturnType<typeof readMergedConfig>>} cfg
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

        const code = result.code;
        const retryable = code === 'DEVICE_IN_USE' || code === 'ERROR_TIMEOUT';
        if (retryable && attempt < max) {
            await sleep(delay);
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
            if (settled) {
                return;
            }
            settled = true;
            clearTimeout(timer);
            resolve(value);
        };

        const timer = setTimeout(() => {
            finish({ ok: false, code: 'EX_TIMEOUT', message: 'print response timeout' });
        }, printTimeoutMs + 5000);

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
    await waitForPendingDisconnect();

    const printTimeoutMs = Math.max(
        Number(job.opts?.timeoutMs ?? 0),
        Number(cfg.printTimeoutMs ?? DEFAULT_TIMEOUT_MS),
        DEFAULT_TIMEOUT_MS,
    );

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

    const maxJobAttempts = 3;

    for (let jobAttempt = 1; jobAttempt <= maxJobAttempts; jobAttempt++) {
        try {
            await waitForPendingDisconnect();

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
                const conn = await connectWithRetry(dev, cfg);
                if (!conn.ok) {
                    await disconnectNow();
                    if (jobAttempt < maxJobAttempts && isConnectRetryable(String(conn.code))) {
                        await sleep(900 * jobAttempt);
                        continue;
                    }
                    return conn;
                }
            }

            if (!_printer) {
                const p = await createPrinterWithRetry(dev, cfg);
                if (!p) {
                    await disconnectNow();
                    if (jobAttempt < maxJobAttempts) {
                        await sleep(900 * jobAttempt);
                        continue;
                    }
                    return { ok: false, code: 'DEVICE_OPEN_FAIL', message: 'createDevice failed after retries' };
                }
                _printer = p;
            }

            const sendRes = await sendXmlAndWait(_printer, job.xml, printTimeoutMs);

            if (sendRes.ok) {
                scheduleIdleDisconnect(cfg);
                return sendRes;
            }

            await disconnectNow();

            if (jobAttempt < maxJobAttempts && isSendRetryable(sendRes.code, sendRes.message)) {
                await sleep(1200 * jobAttempt);
                continue;
            }

            return sendRes;
        } catch (e) {
            await disconnectNow();
            if (jobAttempt < maxJobAttempts) {
                await sleep(1200 * jobAttempt);
                continue;
            }
            return {
                ok: false,
                code: 'EPOS_THROW',
                message: e && e.message ? e.message : String(e),
            };
        }
    }

    return { ok: false, code: 'EPOS_ABORT', message: 'print attempts exhausted' };
}

async function drainQueue() {
    if (_draining) {
        return;
    }
    _draining = true;

    try {
        while (_queue.length > 0) {
            const job = _queue.shift();
            if (!job) {
                continue;
            }

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
