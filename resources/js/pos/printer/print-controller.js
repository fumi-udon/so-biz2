import { PrinterFactory } from './printer-factory.js';
import { resolvePosPrinterStaffMessage } from './pos-printer-staff-messages.js';

/**
 * PrintController — binds Livewire's browser-dispatched print events to
 * the active Printer driver and dispatches the result back. Only one
 * instance should exist per page. Installed from resources/js/app.js.
 *
 * Wire event contract (from Livewire PrinterBridge):
 *   in  → 'pos-trigger-print'   { printJobId, jobKey, xml, opts? }
 *   out → 'pos-print-ack'       { printJobId, ok, code?, message?, staffMessage?, displayCode? }
 *         'pos-print-dispatched'{ printJobId }
 */
class PrintControllerImpl {
    constructor() {
        this._driver = null;
        this._installed = false;
    }

    install() {
        if (this._installed) return;
        this._installed = true;
        this._driver = PrinterFactory.resolveDriver();

        // Livewire 3 dispatches from the component root element with bubbling.
        // Bubbling reaches `document` but not `window`, so listening on `window`
        // never fires for PHP `$this->dispatch('pos-trigger-print', ...)`.
        document.addEventListener('pos-trigger-print', (e) => this._onTrigger(e));
    }

    async _onTrigger(event) {
        const detail = this._extractDetail(event);
        if (!detail) return;
        const { printJobId, jobKey, xml, opts } = detail;
        if (!printJobId || !jobKey || typeof xml !== 'string') {
            this._dispatchAck(printJobId ?? 0, { ok: false, code: 'BAD_EVENT', message: 'missing fields' });
            return;
        }

        this._emitLifecycle('start', printJobId);
        try {
            const res = await this._driver.send(jobKey, xml, {
                ...(opts ?? {}),
                printJobId,
                onDispatched: () => this._dispatchLivewire('pos-print-dispatched', { printJobId }),
            });
            if (res.ok) {
                this._dispatchAck(printJobId, { ...res, staffMessage: null, displayCode: null });
            } else {
                const mapped = resolvePosPrinterStaffMessage(res.code, res.message);
                this._dispatchAck(printJobId, {
                    ...res,
                    staffMessage: mapped.staffMessage,
                    displayCode: mapped.displayCode,
                });
            }
        } catch (e) {
            const mapped = resolvePosPrinterStaffMessage('JS_THROW', e && e.message ? e.message : String(e));
            this._dispatchAck(printJobId, {
                ok: false,
                code: 'JS_THROW',
                message: (e && e.message) ? e.message : String(e),
                staffMessage: mapped.staffMessage,
                displayCode: mapped.displayCode,
            });
        } finally {
            this._emitLifecycle('end', printJobId);
        }
    }

    /**
     * @param {'start'|'end'} phase
     * @param {number} printJobId
     */
    _emitLifecycle(phase, printJobId) {
        try {
            window.dispatchEvent(
                new CustomEvent('pos-print-lifecycle', {
                    detail: { phase, printJobId },
                    bubbles: true,
                }),
            );
        } catch {
            /* ignore */
        }
    }

    _extractDetail(event) {
        if (!event) return null;
        // Livewire 3 dispatches browser events wrapped in { detail: [payload] }
        const raw = event.detail;
        if (raw == null) return null;
        if (Array.isArray(raw)) return raw[0] ?? null;
        return raw;
    }

    _dispatchAck(printJobId, res) {
        this._dispatchLivewire('pos-print-ack', {
            printJobId,
            ok: !!res.ok,
            code: res.code ?? null,
            message: res.message ?? null,
            staffMessage: res.staffMessage ?? null,
            displayCode: res.displayCode ?? null,
        });
    }

    _dispatchLivewire(name, payload) {
        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
            window.Livewire.dispatch(name, payload);
        }
    }
}

const PosPrintController = new PrintControllerImpl();
window.PosPrintController = PosPrintController;
export default PosPrintController;
