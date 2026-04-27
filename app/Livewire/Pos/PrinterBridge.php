<?php

namespace App\Livewire\Pos;

use App\Actions\Pos\Print\CompletePrintJobAction;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

/**
 * Invisible Livewire bridge between the JS print controller and the
 * CompletePrintJobAction on the server. Handles two events dispatched by
 * resources/js/pos/printer/print-controller.js:
 *
 *   pos-print-dispatched  → mark job as "dispatched" (in-flight)
 *   pos-print-ack         → terminal success/failure transition
 *
 * Keeping this as a separate always-mounted component means every Livewire
 * page that needs printing (TableDashboard today, future OrderMonitor,
 * Addition flow, etc.) just opts in by dropping the tag, without having
 * to replicate state wiring in each parent component.
 */
class PrinterBridge extends Component
{
    #[Locked]
    public int $shopId = 0;

    public function mount(int $shopId): void
    {
        $this->shopId = $shopId;
    }

    #[On('pos-print-dispatched')]
    public function onDispatched(mixed $printJobId = null): void
    {
        $id = is_numeric($printJobId) ? (int) $printJobId : 0;
        if ($id < 1) {
            return;
        }

        try {
            app(CompletePrintJobAction::class)->markDispatched($id);
        } catch (Throwable $e) {
            // Silent: markDispatched is best-effort state tracking, not a
            // source of business truth. The ack handler below is authoritative.
        }
    }

    #[On('pos-print-ack')]
    public function onAck(
        mixed $printJobId = null,
        mixed $ok = null,
        mixed $code = null,
        mixed $message = null,
        mixed $staffMessage = null,
        mixed $displayCode = null,
    ): void {
        $id = is_numeric($printJobId) ? (int) $printJobId : 0;
        if ($id < 1) {
            return;
        }

        try {
            if ((bool) $ok) {
                app(CompletePrintJobAction::class)->markSucceeded($id);

                return;
            }

            $codeStr = is_string($code) ? $code : null;
            $msgStr = is_string($message) ? $message : null;
            $staffStr = is_string($staffMessage) ? $staffMessage : null;
            app(CompletePrintJobAction::class)->markFailed($id, $codeStr, $msgStr);

            $display = is_string($displayCode) && $displayCode !== '' ? $displayCode : ($codeStr ?? '');
            $body = $staffStr ?? trim(($display !== '' ? '['.$display.'] ' : '').($msgStr ?? ''));
            if ($body === '') {
                $body = __('pos.print_failed_body_fallback');
            }
            if ($display !== '') {
                $body .= PHP_EOL.PHP_EOL.__('pos.print_error_code_line', ['code' => $display]);
            }
            $body .= PHP_EOL.PHP_EOL.__('pos.print_failed_but_processed_notice');

            Notification::make()
                ->title(__('pos.print_failed_title'))
                ->body($body)
                ->warning()
                ->persistent()
                ->actions([
                    NotificationAction::make('dismiss')
                        ->label(__('pos.print_error_close'))
                        ->button()
                        ->close(),
                ])
                ->send();
        } catch (Throwable $e) {
            // Never let an ack-handler failure throw to the UI.
        }
    }

    public function render()
    {
        return view('livewire.pos.printer-bridge');
    }
}
