/**
 * PrinterInterface — minimal contract every printer driver must implement.
 *
 * Implementations are intentionally small so the Livewire bridge doesn't
 * have to know whether the currently-selected driver is the real ePOS HTTP
 * printer (production) or the Mock (no hardware / dev env).
 *
 *   send(jobKey, xmlPayload, opts?) → Promise<{ ok: true }>
 *                                   | Promise<{ ok: false, code, message }>
 *
 *   - jobKey is the backend's sha256(session_id:revision:intent). Drivers
 *     may (and should) dedupe in-flight requests by jobKey to protect
 *     against accidental double-fires from UI.
 *   - xmlPayload is the ePOS-Print XML produced by
 *     App\Support\Pos\EpsonReceiptXmlBuilder.
 *   - opts.timeoutMs: hard timeout (default 10_000ms). Chosen to prevent
 *     cashier lockout if printer/network hangs.
 */
// eslint-disable-next-line no-unused-vars
export class PrinterInterface {
    async send(/* jobKey, xmlPayload, opts */) {
        throw new Error('PrinterInterface.send must be implemented by driver');
    }
}

export const DEFAULT_TIMEOUT_MS = 10_000;
