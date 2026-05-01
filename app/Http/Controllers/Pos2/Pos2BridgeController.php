<?php

namespace App\Http\Controllers\Pos2;

use App\Http\Controllers\Controller;
use App\Models\TableSession;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * POS V2 から旧 Livewire（ReceiptPreview / ClotureModal）を別タブで開くための薄いブリッジ。
 *
 * @see docs/pos_v2_architecture.md Phase 5 (GATEWAY)
 */
final class Pos2BridgeController extends Controller
{
    public function addition(Request $request, int $session): View
    {
        $ctx = $this->resolveAuthorizedSession($request, $session);

        return view('pos2.bridge.receipt-preview', [
            'shopId' => $ctx['shop_id'],
            'tableSessionId' => $ctx['session_id'],
            'expectedRevision' => $ctx['revision'],
            'intent' => 'addition',
            'escapeUrl' => route('pos2.index'),
        ]);
    }

    public function cloture(Request $request, int $session): View
    {
        $ctx = $this->resolveAuthorizedSession($request, $session);

        return view('pos2.bridge.cloture', [
            'shopId' => $ctx['shop_id'],
            'tableSessionId' => $ctx['session_id'],
            'expectedRevision' => $ctx['revision'],
            'escapeUrl' => route('pos2.index'),
        ]);
    }

    /**
     * 会計確定後のレシートプレビュー（Cloture 完了後に同一タブで遷移）。
     */
    public function receipt(Request $request, int $session): View
    {
        $ctx = $this->resolveAuthorizedSession($request, $session);

        return view('pos2.bridge.receipt-preview', [
            'shopId' => $ctx['shop_id'],
            'tableSessionId' => $ctx['session_id'],
            'expectedRevision' => $ctx['revision'],
            'intent' => 'receipt',
            'escapeUrl' => route('pos2.index'),
        ]);
    }

    /**
     * @return array{shop_id: int, session_id: int, revision: int}
     */
    private function resolveAuthorizedSession(Request $request, int $sessionId): array
    {
        $shopId = $this->resolveShopId($request);
        abort_if($shopId < 1, 403);

        $tableSession = TableSession::query()
            ->where('shop_id', $shopId)
            ->whereKey($sessionId)
            ->first();

        abort_if($tableSession === null, 404);

        return [
            'shop_id' => $shopId,
            'session_id' => (int) $tableSession->id,
            'revision' => (int) $tableSession->session_revision,
        ];
    }

    private function resolveShopId(Request $request): int
    {
        $candidate = (int) ($request->session()->get('pos2.active_shop_id')
            ?? $request->session()->get('kds.active_shop_id')
            ?? env('POS_DEFAULT_SHOP_ID', 0));

        return max(0, $candidate);
    }
}
