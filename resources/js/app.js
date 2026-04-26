import './bootstrap';
import './echo';
import './order-store';
import './pos-draft-store';
import { PosPrintController } from './pos/printer/index.js';

// Install the POS print controller once Alpine/Livewire are present.
// Defer to next tick so window.Livewire is guaranteed to exist when
// PrinterBridge dispatches its first event.
queueMicrotask(() => {
    try {
        PosPrintController.install();
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('[PosPrintController] install failed', e);
    }
});
