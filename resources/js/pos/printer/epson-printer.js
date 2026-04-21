import { PrinterInterface } from './printer-interface.js';
import { enqueueEposPrint } from './epos-device-print-engine.js';

/**
 * Epson TM 系 — {@link enqueueEposPrint} への薄いラッパ（PrinterFactory 互換）。
 */
export class EpsonDevicePrinter extends PrinterInterface {
    constructor(config = {}) {
        super();
        this._config = config;
    }

    /**
     * @param {string} jobKey
     * @param {string} xmlPayload
     * @param {Record<string, unknown>} opts
     */
    async send(jobKey, xmlPayload, opts = {}) {
        return enqueueEposPrint({
            jobKey,
            xml: xmlPayload,
            opts,
            printJobId: Number(opts.printJobId ?? 0),
            onDispatched: typeof opts.onDispatched === 'function' ? opts.onDispatched : undefined,
        });
    }
}

/** @deprecated Use {@link EpsonDevicePrinter} — kept for grep / re-exports */
export const EpsonHttpPrinter = EpsonDevicePrinter;
