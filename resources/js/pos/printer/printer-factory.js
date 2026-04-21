import { EpsonDevicePrinter } from './epson-printer.js';
import { MockPrinter } from './mock-printer.js';

/**
 * PrinterFactory — single source of truth for choosing a driver.
 *
 * Resolution order:
 *   1. window.posPrinterConfig.driver (set by server: 'mock' | 'epson').
 *   2. ?posPrinter=<driver> query parameter (for on-the-fly QA override).
 *   3. Default: 'mock' (safe until real printer config exists).
 *
 * Production Epson path uses {@link window.PosConfig} (ePOSDevice / 8043).
 * Legacy SOAP URL on posPrinterConfig is still accepted as a fallback for IP/port.
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
            const hasPos = window.PosConfig && window.PosConfig.ip;
            const hasLegacyUrl = config && config.url;
            if (!hasPos && !hasLegacyUrl) {
                // eslint-disable-next-line no-console
                console.warn(
                    '[PrinterFactory] Epson driver selected but window.PosConfig (ip) / url missing; falling back to MockPrinter.',
                );
                return new MockPrinter({
                    simulateLatencyMs: (config && config.mockLatencyMs) || 250,
                });
            }
            return new EpsonDevicePrinter({
                timeoutMs: (config && config.timeoutMs) || 10_000,
            });
        }

        return new MockPrinter({
            simulateLatencyMs: (config && config.mockLatencyMs) || 250,
        });
    }
}
