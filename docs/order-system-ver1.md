# 店内モバイルオーダー・オーダーシステム Ver1（正式記録）

**文書種別:** 要件・運用・通信境界の確定版（実装の単一ソースの参照用）  
**対象:** Söya業務システムに統合する「顧客向けオーダーUI」および将来接続するテーブルPOS／キッチンKDSとの境界  
**Ver1 の位置づけ:** ゲストUIモック＋ローカル状態（DB接続・本番送信は Ver2 以降）

---

## 1. 運用スケール（現場前提）

| 項目 | 値 |
|------|-----|
| テーブル数 | 15 |
| 1日の来店人数（目安） | 約 100 名 |
| 端末 | 客は各自のスマホ（100% スマホ想定） |
| QR | 卓ごとにユニークQR（同一卓に複数端末が合流しうる） |

---

## 2. 業務フロー（確定・順序固定）

以下は **変更不可のオペレーション順** として記録する。

1. 客が卓QRをスキャンし、メニューで商品を選ぶ。
2. 客が **カート画面からオーダーボタン** を押下し、注文を送信する。
3. **テーブルシステム（ホール用POS）** が注文を受信する。  
   - **この区間ではリアルタイム通信（Pusher / WebSocket / Echo 等）を使用しない。**  
   - 更新は **F5 手動更新** または **短周期ポーリング（例: 10s）** 等の非プッシュ方式でよい（Ver1 方針）。
4. 客側画面に案内を表示する（文言の意図は以下）。  
   - 例: 「注文が送信されました。Surveur にお声がけをしてください。注文復唱後注文が確定されます。」
5. 客がサーバーを呼ぶ（例: 「注文しましたー。確認」）。
6. スタッフが客と内容を確認し、テーブルシステムで **「Recu staff」** を押下する。  
   - **この操作が初めて注文を確定（Human filter）** とする。
7. **確定後のみ**、**リアルタイム（Pusher 等）でキッチンシステム（KDS）へ** データを送る。

---

## 3. 通信アーキテクチャ（境界の正式定義）

| 区間 | Ver1 方針 |
|------|-----------|
| 客端末 → テーブルシステム | **非リアルタイム**（プッシュ禁止。ポーリング／手動更新可） |
| テーブルシステム → キッチンシステム | **Recu staff 確定後に限りリアルタイム**（Pusher / Reverb 等。private チャンネル設計は別紙） |

**誤解禁止:** 「カートや客→POS を Phase3 でリアルタイム化する」ことは **本要件に含まれない。**

---

## 4. データ・状態の魂（設計原則）

- **Human filter:** `placed`（送信）と `confirmed`（Recu staff）を厳密に分離。キッチンへ飛ばすのは **confirmed 以降**。
- **Immutability（将来接続時）:** 確定時に商品名・単価等を行へスナップショット（別設計書で詳細化）。
- **同一QR・複数端末:** Ver1 はローカルカート中心。Ver2 以降で `GuestSession` / サーバドラフト同期を導入する前提でUIを拡張可能にする。

---

## 5. 技術スタック（Ver1 ゲストUI）

- **TALL:** Livewire 3 + Alpine.js + Tailwind v4（Filament アセットと混在禁止）
- **レイアウト:** `layouts/guest-order` のみ（`layouts.client-order` 等の管理系と分離）
- **Hydration Standard（必須）:**
  - 巨大JSONを `x-init` / `x-data` 属性へ直埋め込みしない。
  - `<script type="application/json" id="guest-order-data">` + `alpine:init` で `window.Alpine.store` を登録。
  - `import Alpine from 'alpinejs'` は禁止（Livewire 3 と二重インスタンス化のため）。
- **Tailwind v4:** `resources/css/app.css` の `@source` でスキャン対象を管理（ルート `tailwind.config.js` はメインUIでは使用しない）。

詳細ルールはリポジトリの `.cursorrules` を参照。

---

## 6. Ver1 実装済みスコープ（現時点のモック）

- ルート: `GET /guest/menu/{tenantSlug}/{tableToken}`（`SetGuestLocale` 適用）
- ダミー `catalog` / `theme` / `translations`
- カスタムシート、ローカルカート行、`mergeKey` 集約、演出（カート発光・飛翔パーティクル等）
- **テーブルPOS受信・Recu staff・KDS・Pusher は未接続**（本ドキュメントのフローは将来接続時の契約）

---

## 8. Phase 1.5（カートドロワー UI）

- 同一ページ内で「Voir le panier」相当の CTA からカートドロワーを開き、行の数量変更・削除・バスケット全消去（二段タップ）を行う。送信は **Ver2** でテーブルPOS向け HTTP に接続するまで、ブラウザは `buildTransmissionDraft()` 相当の JSON と `guest-order:draft-ready` イベントのみ発行する。
- 卓 `tableToken` / `tenantSlug` / `clientSessionId`（sessionStorage）はペイロードに含め、バックエンド実装時の相関用とする。

---

## 7. 改訂履歴

| 日付 | 内容 |
|------|------|
| 2026-04-16 | Ver1 正式記録として初版作成（客→POS 非RT、POS→KDS は確定後RT を明文化） |
| 2026-04-16 | Phase 1.5: カートドロワー・ドラフト JSON / イベント（キッチン直送なし）を追記 |
