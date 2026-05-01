<?php

namespace App\Livewire\Pos;

use Livewire\Attributes\On;
use Livewire\Component;

/**
 * ブリッジ用タブで Livewire グローバルイベントを受け、親ウィンドウ（POS V2）へ postMessage する。
 * ReceiptPreview / ClotureModal 本体は変更しない。
 */
class Pos2BridgeMessenger extends Component
{
    #[On('receipt-preview-printed')]
    public function onReceiptPreviewPrinted(mixed $table_session_id = null, mixed $intent = null): void
    {
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        $intentStr = is_string($intent) ? $intent : (string) ($intent ?? '');
        $this->dispatchToParent([
            'action' => 'receipt-preview-printed',
            'table_session_id' => $sid > 0 ? $sid : null,
            'intent' => $intentStr !== '' ? $intentStr : null,
        ]);
    }

    #[On('close-receipt')]
    public function onCloseReceipt(): void
    {
        $this->dispatchToParent(['action' => 'close-receipt']);
    }

    #[On('pos-settlement-completed')]
    public function onPosSettlementCompleted(
        mixed $table_session_id = null,
        mixed $open_receipt_preview = null,
        mixed $settlement_trace_id = null,
    ): void {
        $sid = is_numeric($table_session_id) ? (int) $table_session_id : 0;
        $open = $open_receipt_preview === true || $open_receipt_preview === 1 || $open_receipt_preview === '1';
        $this->dispatchToParent([
            'action' => 'pos-settlement-completed',
            'table_session_id' => $sid > 0 ? $sid : null,
            'open_receipt_preview' => $open,
        ]);

        if ($open && $sid > 0) {
            $url = route('pos2.bridge.sessions.receipt', ['session' => $sid]);
            $this->js('window.location.href = '.json_encode($url));
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchToParent(array $payload): void
    {
        $json = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->js(<<<JS
(function(){
  try {
    var p = {$json};
    if (window.opener && !window.opener.closed) {
      window.opener.postMessage(Object.assign({ type: 'pos2-bridge' }, p), window.location.origin);
    }
  } catch (e) { console.warn('[pos2-bridge]', e); }
})();
JS);
    }

    public function render()
    {
        return view('livewire.pos.pos2-bridge-messenger');
    }
}
