import { EpsonHttpPrinter } from './epson-printer.js';
import { MockPrinter } from './mock-printer.js';

/**
 * PrinterFactory — single source of truth for choosing a driver.
 *
 * Resolution order:
 *   1. window.posPrinterConfig.driver (set by server: 'mock' | 'epson').
 *   2. ?posPrinter=<driver> query parameter (for on-the-fly QA override).
 *   3. Default: 'mock' (safe until real ePOS URL is configured).
 *
 * When 'epson' is selected but window.posPrinterConfig.url is missing,
 * we fall back to 'mock' and log a loud warning — we'd rather print
 * a preview than silently drop receipts at the counter.
 */
export class PrinterFactory {
    static resolveDriver(config = window.posPrinterConfig) {
        const fromQuery = (() => {
            try {
                return new URL(window.location.href).searchParams.get('posPrinter') || null;
            } catch {
                return null;
            }
        })();

        const requested = (fromQuery || (config && config.driver) || 'mock').toLowerCase();

        if (requested === 'epson') {
            const url = config && config.url;
            if (!url) {
                // eslint-disable-next-line no-console
                console.warn('[PrinterFactory] Epson driver selected but url missing; falling back to MockPrinter.');
                return new MockPrinter({});
            }
            return new EpsonHttpPrinter({
                url,
                timeoutMs: (config && config.timeoutMs) || 10_000,
            });
        }

        return new MockPrinter({
            simulateLatencyMs: (config && config.mockLatencyMs) || 250,
        });
    }
}
