# Söya POS V2 Architecture Core (GENESIS)
**Version:** 1.4 (April 2026)

## 1. 根本的な設計思想 (Core Philosophy)
このドキュメントは、Söya POS V2を開発する上での「絶対に変わらないルール」を定義する。AIアシスタント（Cursor等）は、本ドキュメントのルールをすべての提案・コード生成において**最優先**しなければならない。

*   **完全な分離 (Strict Isolation):** POS V2専用画面には、既存のLivewireおよびAlpine.jsを一切読み込ませない。DOMの主導権争いを物理的に排除する。
*   **引き算の設計 (Subtract Complexity):** KDS V2 (Vue 3 + Pinia) の成功モデルを踏襲し、フロントエンドは「軽量な状態管理」に徹する。複雑な業務ロジック（税計算、決済、印刷連携）は既存のLaravel（PHP）側に委ねる。
*   **オフライン耐性と爆速UI (Zero Latency):** OVHサーバーの遅延（約300ms）を隠蔽するため、商品追加やテーブル選択はクライアントサイド（Pinia）で即時反映する「Optimistic UI」を採用する。

## 2. 技術スタックの制約 (Technology Stack Boundaries)
POS V2の開発において、以下の技術選定を厳守すること。代替技術の提案は却下する。

*   **フロントエンド:** Vue 3 (Composition API) + Inertia.js + Pinia + Tailwind CSS
*   **バックエンド:** Laravel 11 + Axios (API通信用)
*   **永続化:** `localStorage`（マスタキャッシュのほか、`useDraftStore` が卓セッションに紐づくドラフトキー用に使用。**UI は複数行カートを貯めない**が、キー規約・掃除契約は維持する）
*   **禁止事項:**
    *   POS V2領域 (`/pos-v2` 配下) における Livewire コンポーネントの使用
    *   POS V2領域における Alpine.js の使用 (`x-data` 等)
    *   Bladeテンプレート内での複雑なロジック処理（Inertia経由のデータ渡しのみとする）

## 3. データと状態管理のルール (Data & State Rules)
*   **SSOT (Single Source of Truth):** クライアントサイドの状態はすべてPiniaで一元管理する。
*   **ドラフトストアのキー（卓セッション単位）:** `useDraftStore` の `shopId` / `tableSessionId` / `pendingTableId` と、オプションの LocalStorage キーは**必ずセッション単位**で独立させること（前客データ混入防止）。
    *   ✅ 正： `pos_draft_{shopId}_{tableSessionId}`
    *   ❌ 誤： `restaurant_table_id` のみをキーにする（前客のデータ復活リスクがあるため禁止）
*   **1 品即決（買い物カゴ廃止）:** オペレーション上、**複数商品を `lines` に溜めてから一括送信する UI は持たない**。単品タップおよび `ConfigModal` の確定はいずれも **`submitLines([cartLine])` で即コミット**する。送信パイプライン成功後は `lines` は常に空（`clearDraft`）で、複数行カート状態を物理的に作らない。
*   **マスタデータ同期:** 頻繁な通信を避けるため、起動時に全マスタをJSONで取得しLocalStorageに保持する。日中の更新は「手動同期（Syncボタン）」を運用ルールとする。

## 4. アーキテクチャの境界線 (Architectural Boundaries)
POS V2が「担当する領域」と「担当しない（既存に任せる）領域」を明確に分ける。

*   **POS V2 (Vue/Inertia) が担当する領域:**
    *   テーブルマップの表示と状態（UI Status）のレンダリング
    *   テーブル選択（タップ）時の0ms切り替え
    *   商品一覧・カテゴリタブの表示
    *   商品追加モーダル（トッピング・スタイル選択）と、確定行の **`CartLineSnapshot` 生成〜即送信（`submitLines`）**
    *   バックエンドへの注文行の送信（`POST .../orders`、行配列は通常 1 要素）
*   **既存Laravel (Livewire) が担当する領域:**
    *   会計処理（ClotureModal）
    *   追加注文・レシート印刷（Addition / Print）
    *   税金・割引等の複雑な金額計算（`PricingEngine`）
    *   KDSへのブロードキャスト（`KdsBroadcastService`）

## 5. Cursor / AIへの指示 (Instructions for AI)
このファイルを参照してコードを生成する際は、以下のステップを遵守すること。
1.  新しい機能を実装する前に、本ドキュメントの「技術スタック」と「境界線」に違反していないか確認する。
2.  バックエンド（Laravel）側でV2用のAPIやコントローラーを追加する場合は、既存のロジック（`TableDashboardQueryService` や `TableActionHost` 内の処理）を極力流用し、重複コードを避けること。
3.  DOM操作はすべてVueの仮想DOMに任せ、直接的な `document.getElementById` やjQueryのような操作は行わないこと。
4.  端末別 UI（タブレット優先・スマホ第2章・Flex サンドイッチ等）は **§9** に従うこと。
5.  Phase 4.5 SYNAPSE（モード分離・卓 API・セッション注文）は **§10** に従うこと。

## 6. API フィールド名規約 (API Field Naming Contract)

> **[CRITICAL] AIへの指示:** 以下のフィールド名は本番バグの直接原因になった実績があるため、コード生成・レビュー時に必ず照合すること。

### 6-1. bootstrap API (`GET /pos2/api/bootstrap`) のレスポンス形式

**商品 (`menuItems[]`) の価格フィールド:**

| フィールド名 | 型 | 意味 |
|---|---|---|
| `from_price_minor` | integer | **スタイル選択なし時のベース価格（millimes）**。`price_minor` や `base_price_minor` とは別名。 |
| `menu_category_id` | integer | 所属カテゴリID。`category_id` ではない。 |

**トッピング (`options_payload.toppings[]`) の価格フィールド:**

| フィールド名 | 型 | 意味 |
|---|---|---|
| `price_delta_minor` | integer | トッピング追加金額（millimes）。`price_minor` ではない。 |

**スタイル (`options_payload.styles[]`) の価格フィールド:**

| フィールド名 | 型 | 意味 |
|---|---|---|
| `price_minor` | integer | スタイル選択時の確定価格（millimes）。ベース価格に加算するのではなく、これ自体がそのスタイルの単価。 |

### 6-2. カートスナップショット (`CartLineSnapshot`) のフィールド名

フロント内部でのみ使用するスナップショット行は、APIとは**意図的に命名を分けている**。マスタの `from_price_minor` → スナップショットの `base_price_minor`、トッピングの `price_delta_minor` → スナップショットの `price_minor`、と正規化して格納する。

| スナップショットフィールド | 元のAPIフィールド | 変換方向 |
|---|---|---|
| `base_price_minor` | `from_price_minor` | masterItem → snapshot |
| `topping_snapshots[].price_minor` | `price_delta_minor` | toppings → snapshot |

### 6-3. 単価合計（`total_unit_price_minor`）の計算仕様（会計・KDS前提）

**スタイル（必須セレクト含む）が選ばれている場合:**  
`options_payload.styles[].price_minor` は **そのスタイルの確定単価**であり、`from_price_minor` への加算ではない。  
単価合計は **`style.price_minor` + 選択トッピングの `price_delta_minor` 合計** とする。`from_price_minor` は単価計算に含めない。

**スタイルが選ばれていない場合（単品・トッピングのみ等）:**  
単価合計は **`from_price_minor` + 選択トッピングの `price_delta_minor` 合計** とする。

**(禁止パターン — 二重計上バグ):**
```javascript
// ❌ NG: スタイル価格はベースに「足す」のではない
const total = from_price_minor + style.price_minor + toppings;
```

**(正しいパターン):**
```javascript
// ✅ OK
const total = selectedStyle
  ? selectedStyle.price_minor + sumToppingDeltas
  : from_price_minor + sumToppingDeltas;
```

実装の唯一の関所: `resources/js/pos2/utils/cartLineBuilder.js` の `buildCartLineSnapshot`。モーダル内プレビューは `useMenuStore` の `modalTotalUnitPriceMinor` で同じ規則を適用すること。

**(禁止パターン)** 以下のコードは全て価格が 0 になるバグを引き起こした実績がある:
```javascript
// ❌ NG: API は from_price_minor を返す
const price = item.price_minor ?? 0;

// ❌ NG: トッピングは price_delta_minor を返す
const toppingPrice = topping.price_minor ?? 0;

// ❌ NG: 商品→カテゴリのマッピングは menu_category_id を使う
const catId = item.category_id;
```

**(正しいパターン)**:
```javascript
// ✅ OK
const price = item.from_price_minor ?? item.price_minor ?? 0;
const toppingPrice = topping.price_delta_minor ?? topping.price_minor ?? 0;
const catId = item.menu_category_id;
```

---

## 7. 注文送信エンジン (API Transmission Contract)

注文の送信は、二重送信や状態の不整合を防ぐため、以下のルールを厳守する。

*   **エントリポイント:** `useDraftStore.submitLines(linesInput, debugEnabled)`。`linesInput` は **`CartLineSnapshot[]`（現場仕様では主に長さ 1）**。旧来の「カートに複数行貯めてから一括送信する `submitOrder`」は廃止済み。
*   **完全なUIロック:** 送信処理中（`isOrderSubmitting` === `true`）は、商品グリッド（CategoryRail / ProductGrid）およびモーダル確定ボタンを含む**画面全体の操作を半透明オーバーレイ等で物理的にロック**すること。
*   **非同期POSTとCSRF:** 送信は `axios.post` を用い、必ず `Accept: application/json` と CSRF トークン（`X-CSRF-TOKEN` および Cookie 由来の `X-XSRF-TOKEN`）を付与すること。フルリロードは禁止。
*   **ペイロードと冪等性の担保:** API へは `{ lines: CartLineSnapshot[], client_submit_id }`（セッション URL または空卓テーブル URL）を送信する。`client_submit_id` には送信のたびに一意の UUID を付与し、将来的なサーバー側での重複排除の布石とする。
*   **楽観クリア:** `submitLines` は **`appendOptimisticStaffSubmit` の直後に `clearDraft` を呼ぶ**（同一同期ブロック内）。よって UI 上の `lines` / 該当 LocalStorage キーは **POST 完了を待たずに空**になる。サーバー権威の確定は **HTTP 201** と続く **`GET .../orders` / `GET table-dashboard`** で行う。2xx でも 201 以外は成功扱いにしない（契約の揺れ防止）。
*   **Traceの統一:** デバッグログは必ず `debugStore.pushTrace` を使用すること（`addTrace` は使用しない）。PII やログ肥大化を防ぐため、`order.submit.started` にはスナップショット全文ではなく、行数・合計金額（minor）・`client_submit_id`・`table_session_id` などの要約を記録すること。

### 7-1 Phase 4 調査ロジック（分離）

バグ時にフロント Trace・Debug 画面・`posv2.log` を突き合わせて原因を切り分けられるように、注文送信まわりの調査だけを専用モジュールに隔離する。

*   **フロント:** `resources/js/pos2/utils/orderSubmitInvestigation.js` — `pushOrderSubmitTrace` / `recordLastOrderSubmitAudit`。**`useDraftStore.submitLines`（および `_runOptimisticSubmitBackground`）から呼ぶ。** 内部は `try-catch` と `page.props.auth.debug`（`POS2_DEBUG`）ガードで、本処理を止めない。
*   **Trace イベント名:** `order.submit.started` / `order.submit.request_sent` / `order.submit.http_received` / `order.submit.succeeded` / `order.submit.failed` / `order.submit.skipped`。相関用に `trace_id`（`nextTraceId('order-submit')`）と `client_submit_id` を載せる。`lines` 配列の全文は Trace に載せない。
*   **DebugPanel:** ドラフトストア（Cart 相当）タブに `lastOrderSubmit`（`outcome`, `http_status`, `duration_ms`, `trace_id` 等）と `isOrderSubmitting` を表示し、Trace タブの時系列と対応付け可能にする。
*   **サーバ（posv2.log）:** `Pos2Controller::submitOrderStub` は `config('app.pos2_debug')` が true のときのみ `Log::channel('pos2')` に `order.submit.received` 等の要約（`client_submit_id`, `line_count`, `shop_id`, `table_session_id`, `ip`）を記録する。リクエストボディ全文はログに出さない。

**エンドポイント（Phase 4 スタブ）:** `POST /pos2/api/orders`（`pos2.auth` 配下）。実装参照: `Pos2Controller::submitOrderStub`。

---

## 8. デバッグと調査コードの鉄則 (Debugging & Safe Logging Policy)
Söya POS V2 は RTT 276ms 以上の高レイテンシ環境で稼働するため、不具合発生時の即時特定を目的とした「調査コード（TraceやLog）」を随所に組み込む。
ただし、**「調査コードの失敗が原因でPOSのメインロジックが停止する（本末転倒）」事態を物理的に防ぐ**ため、以下の完全分離ルールを標準とする。

*   **環境変数ガード (Environment Guard):**
    すべての調査用コード（DebugPanelへのTrace追加、およびバックエンドの `posv2.log` への書き込み）は、必ず `POS2_DEBUG=true` の環境でしか実行されないように分岐すること。
*   **本番死守 (Non-intrusive Try-Catch):**
    調査コードの内部でパースエラーや参照エラーが起きても、呼び出し元の処理（テーブル選択、商品タップ・モーダル確定による注文送信など）を決して中断させないこと。すべての調査コードは `try-catch` で保護し、エラー時は安全に握りつぶすこと。

**【フロントエンドの実装例（Inertia 共有の debug フラグ推奨）】**
```javascript
// メインロジック（絶対に止めてはいけない）
state.selectedTableId = tableId;

// 調査ロジック（分離）— POS2 は page.props.auth.debug（= Laravel POS2_DEBUG）を使用
if (page.props.auth?.debug === true) {
  try {
    debugStore.pushTrace('table.selected', { tableId });
  } catch (e) {
    console.warn('Trace failed, but POS continues.', e);
  }
}
```

---

## 9. 端末別 UI ロードマップ（タブレット優先・スマホは第2章）

現場（チュニジア・小タブレット・忙しいフロア）を最優先し、**まずタブレット向け UI の完成に集中**する。スマホは同一画面を無理に縮小表示せず、**アプリ完成後の Version 2 として「スマホ専用レイアウト」**を別途実装する。

### 9-1. 第1章（当面のスコープ）: タブレット UI

*   レイアウト・タップ領域・可読性は **小さめタブレット**を主ターゲットとする。
*   本章で確定したタブレット用コンポーネント構造・余白・タイポを、後続のスマホ章で**破壊しない**こと。スマホは **ルート分岐または専用レイアウトラッパ**で隔離し、既存の `Index.vue` 等のタブレット用マークアップを書き換えて二重責務にしない。

### 9-2. 第2章（Version 2）: スマホ Web 専用 UI

*   **Dynamic Viewport:** スマホの URL バー伸縮によるレイアウト崩れを避けるため、ルート枠は **`h-[100dvh]`**（プロジェクトの Tailwind トークン方針に合わせ `min-h-[100dvh]` を併用してよい）を基準にし、**画面内に 100% 収める**ことを前提とする。
*   **パターン:** ネイティブアプリ型の **「Flexbox サンドイッチ構造」**（`fixed` / `sticky` を極力使わず、z-index 衝突を物理的に避ける）。

**推奨ルート構造（スマホ専用ルート内）:**

| 領域 | Tailwind の考え方 | 内容 |
|------|-------------------|------|
| 全体枠 | `h-[100dvh] flex flex-col overflow-hidden`（背景はプロジェクトのライト/ダークで明示） | 子は縦積みのみ。はみ出しでボタンとリストが重ならないようにする。 |
| 上部 | `shrink-0` + `flex overflow-x-auto` | テーブル選択を**横スクロール可能なチップ**で全卓表示。選択中は背景を強調（例: 濃い背景＋明るい前景）、非選択は薄いトーン。 |
| 中間 | `flex-1 min-h-0 overflow-y-auto` + 高密度（例 `p-1`、`text-xs`） | **注文リストのみ**が縦スクロール。リストが下部アクションの下に潜るバグを防ぐ。 |
| 下部 | `shrink-0` + `grid grid-cols-5 gap-1 p-2 border-t` | **親指が届く**五大アクションバー。iOS セーフエリアは `padding-bottom: env(safe-area-inset-bottom)`（Tailwind の `pb-[max(0.5rem,env(safe-area-inset-bottom))]` 等）を必ず考慮。 |

**五大アクション（アイコンのみ・ラベルはツールチップ等で補足可）:** `[Sync]`、`[Add]`、`[Reçu Staff]`、`[Addition]`、`[Clôture]`。

*   **アイコン:** Filament / Blade ではなく **Vue 側**では **Heroicons（または既存プロジェクトと同一の SVG アイコンセット）**で統一し、見た目の一貫性を保つ。

### 9-3. ガードレール（スマホ章）

*   **`fixed` / `sticky` の乱用禁止:** レイアウトの主軸は **Flex の三層（上 shrink-0 / 中 flex-1 min-h-0 scroll / 下 shrink-0）**とする。どうしてもオーバーレイが必要な場合のみ例外とし、その旨を PR で明示する。
*   **タブレット回帰:** スマホ用の変更が **タブレット用ブレークポイント・既存グリッド**に影響しないよう、ビルド後にタブレット幅で必ず確認する。
*   **カラー・コントラスト:** `.cursorrules` の通り、ライト/ダーク両方で前景色を明示すること。

---

## 10. Phase 4.5 SYNAPSE（モード分離・ゲートウェイ API）

旧 POS の集約・注文・Recu と同一ドメインを **JSON ゲートウェイ**で Vue に供給する。Livewire / Alpine は POS V2 に持ち込まない。

### 10-1. フロントのモード（Pinia）

*   **`usePos2SessionUiStore`:** `uiMode: 'monitoring' | 'adding'`、選択卓、`activeTableSessionId`、`dashboardTiles`（`GET /pos2/api/table-dashboard`）、`sessionOrdersPayload`（`GET /pos2/api/sessions/{id}/orders`）、`sessionRevision`。
*   卓選択直後は必ず **`monitoring`**。`[+ Add]` のみが **`adding`** へ遷移。`Back` で卓一覧へ戻る際はストアをリセットしダッシュボードを再取得する。

### 10-2. `GET /pos2/api/table-dashboard`

*   **権威:** [`TableDashboardQueryService::getDashboardData`](app/Services/Pos/TableDashboardQueryService.php)（`TableTileAggregate::toArray()` と同一キー）。
*   **レスポンス（概要）:** `{ shop_id, tiles: [...], generated_at, schema_version }`。タイルの `uiStatus` は [`TableStatusGrid::tileSurfaceClasses`](app/Livewire/Pos/TableStatusGrid.php) と同じ意味（Vue は [`tileUiClasses.js`](resources/js/pos2/utils/tileUiClasses.js) で配色）。

### 10-3. `GET /pos2/api/sessions/{id}/orders`

*   **権威:** 当該 `table_sessions.id` が `shop_id` に属することを検証のうえ `PosOrder` + `order_lines` を返す。
*   **主要キー:** `table_session_id`, `restaurant_table_id`, `session_revision`, `has_unacked_placed`（**旧 POS `TableActionHost::getHasUnackedPlacedProperty` と整合**: いずれかの `PosOrder.status === placed` が真）、`orders[]`（各 `ordered_by`: `guest` は [`GuestOrderIdempotency`](app/Models/GuestOrderIdempotency.php) に `pos_order_id` がある場合のみ、それ以外は `staff`）、`lines[].line_status`, `lines[].is_unsent`（**KDS / Recu 前の行**: `OrderLineStatus::Placed` のとき真＝右ペインで「KDS前」表示。`confirmed` / `cooking` / `served` 等では偽＝「KDS送信済」）。各行は **`product_name`**（`snapshot_name`）、**`style_name`** / **`topping_names[]`**（`snapshot_options_payload` 由来）を返し、Vue は高密度 2 行表示に使う。
*   **右ペイン表示:** ゲスト経由の注文と `POST .../orders`（Add to Table）は **同一 `orders[]` にマージ**され、行ごとに `is_unsent` で KDS 送信前後を判別する（Vue: [`SessionRightColumn.vue`](resources/js/pos2/components/SessionRightColumn.vue)）。**下段の「未送信ドラフト専用」UI は持たない**（旧 `CartPanel` 廃止）。スタッフ送信直後の仮行は **`appendOptimisticStaffSubmit` が `sessionOrdersPayload` に足した行**としてこの一覧に現れ、GET 成功後に権威データへ置換される。

### 10-4. `POST /pos2/api/sessions/{id}/orders`

*   **権威:** ドラフト行ごとに [`AddPosOrderFromStaffAction::execute`](app/Actions/Pos/AddPosOrderFromStaffAction.php)（1 行＝1 注文ヘッダの既存モデル）。リクエストは `{ client_submit_id?, lines: CartLineSnapshot[] }`（`product_id` / `qty` / `selected_option_snapshot` / `topping_snapshots` をサーバーで再検証）。**実装注意:** `validate()` の戻り値だけを `lines` に使うとスナップショットが欠落する。検証後は **`$request->json('lines')`** で JSON 本文から全文を渡す（`input('lines')` だけではネストが欠ける環境がある）（[`Pos2SessionController::submitDraftOrders`](app/Http/Controllers/Pos2/Pos2SessionController.php)）。
*   **成功:** HTTP **201**。ボディに `order_ids`, `session_revision`, `table_session_id`。成功後は [`TableDashboardQueryService::forgetCachedDashboard`](app/Services/Pos/TableDashboardQueryService.php) を呼ぶ。
*   **失敗（業務バリデーション）:** HTTP **422**。JSON の `message` を人間可読で返す。フロント（`ConfigModal` / `Index.onAddSimple`）は **同文言をアラート表示**する。楽観パイプラインでは先に `clearDraft` 済みのため、POST 失敗時は **`removeOptimisticStaffSubmit` と送信直前スナップショットへの `lines` ロールバック**（通常は空配列）＋アラートで整合を取る（部分成功は行わず、DB は外側トランザクションでロールバック）。
*   **楽観 UI（0ms）:** `submitLines` 内で **`usePos2SessionUiStore.appendOptimisticStaffSubmit`** により、**`axios.post` を発行する前の同一同期ブロック**で `sessionOrdersPayload.orders` に仮注文（`id: opt:{client_submit_id}`・`is_unsent: true`・`ordered_by: staff`）をマージする。続けて **`clearDraft`** で `useDraftStore.lines` と該当 LocalStorage を空にする。`POST` / 成功後の `GET .../orders` / `GET table-dashboard` は **`useDraftStore._runOptimisticSubmitBackground` が `void` で実行**し、`applySessionOrdersJson` で仮行を権威データに置換する。POST 失敗時のみ仮行除去＋`lines` ロールバック＋アラート。詳細は **§10-11**。

### 10-5. `POST /pos2/api/sessions/{id}/recu-staff`

*   **権威:** [`RecuPlacedOrdersForSessionAction::execute`](app/Actions/RadTable/RecuPlacedOrdersForSessionAction.php)。ボディ: `{ expected_session_revision: int }`。
*   **競合:** `RevisionConflictException` → HTTP **409**、`code: REVISION_CONFLICT`（[`technical_contract_v4.md`](docs/technical_contract_v4.md) §5 準拠）。
*   **KDS ベル:** Action 内部の `DB::afterCommit` → [`KdsBroadcastService::notifyOrderConfirmed`](app/Services/Kds/KdsBroadcastService.php)（旧 POS と同一経路）。

### 10-6. 「Send To KDS」ボタン

*   **表示条件:** `has_unacked_placed === true`（旧 POS の Recu 可能条件と一致）。
*   **押下:** `recu-staff` を呼び、成功後に `GET .../orders` と `GET table-dashboard` を再取得する。

### 10-7. タブレット分割 UI（左グリッド + 右詳細）

*   **`Index.vue`:** `md+` で **左に `TableGrid`（常駐）**、**右に注文・操作**の 2 カラム。卓未選択時は右にプレースホルダのみ。
*   **`TableGrid`:** **3 列固定**（`grid-cols-3`）。タイルは卓名・commandes 数・合計 DT（`sessionTotalMinor`）を表示。選択中は **amber ring**（視認性）。`layout-variant="split"` で左カラム用のコンパクトヘッダ。
*   **右ヘッダ:** 卓名バッジ・更新（`fetchTableDashboard` + `fetchSessionOrders`）、**Ajouter**（`adding` へ）、**Reçu staff**（`has_unacked_placed` 時のみ有効、`recu-staff`）。`SessionRightColumn` は `hide-kds-banner` で上部 Recu ストリップを重複表示しない。
*   **注文リスト:** `flex-1 min-h-0 overflow-y-auto` のみスクロール（`sticky` 乱用禁止。§9 スマホ章との整合）。
*   **`adding` モード時:** 右カラム内を `lg:flex-row` で **メニュー（左）+ `SessionRightColumn`（右）**。`SessionRightColumn` は **確定注文一覧のみ**（下段のドラフト枠・`CartPanel` は廃止）。商品タップまたはモーダル **Add to Table**（ラベル）で即 `submitLines` → `POST .../orders`（KDS は飛ばず `Placed` のみ）。成功時は `sessionUi.exitToMonitoring()` でメニューを閉じ、**`monitoring`** に戻る。

### 10-8. セッション無しの卓

*   アクティブ `table_sessions` が無い卓でも **Add to Table** は `POST /pos2/api/tables/{restaurant_table_id}/orders` で [`TableSessionLifecycleService::getOrCreateActiveSession`](app/Services/Pos/TableSessionLifecycleService.php) によりセッション確定後に `Placed` 登録する。フロントは `pendingTableId` で **空卓コンテキスト**を保持し（商品行は溜めない）、201 の `table_session_id` を受けたら **`patchActiveSessionId` のみ**で同期する（`syncSelectionFromTile` は `sessionOrdersPayload` を null にするため送信完了フローでは使わない）。

### 10-9. Add to Table トラブルシュート（調査メモ）

*   **`Style selection is required.`**  
    *   送信行に **スタイル必須商品の「スタイルなしスナップショット」** が含まれると、POST 内で **422** となり **全体ロールバック**する（1 行送信でも同様）。  
    *   対策: `rules.style_required === true` の商品は **`ProductGrid` / `Index.onAddSimple` で必ず ConfigModal** へ。送信前に `useDraftStore.submitLines` がマスタ照合でブロック（日本語メッセージ）する。  
    *   API は `selected_option_snapshot.id` または **`selectedOptionSnapshot`**（camel）からスタイル ID を解決する（`Pos2SessionController::extractStyleIdFromLineRow`）。
*   **201 なのに注文一覧が空振り**  
    *   以前は `GET .../orders` 失敗時に `sessionOrdersPayload` を `null` にしてしまい **成功直後の表示まで消える**ことがあった。現在は **エラーのみ記録しペイロードは保持**（`usePos2SessionUiStore.fetchSessionOrders`）。  
    *   切り分け: Network で **POST 201** → **GET 200** と `orders[]` の件数を確認。

### 10-10. localStorage 掃除（開発）

*   **`POS2_DEBUG` / `auth.debug` が真のときのみ** 右上にハンバーメニュー（[`Pos2AppMenu.vue`](resources/js/pos2/components/Pos2AppMenu.vue)）。**Dev clean up** は先に **`POST /pos2/api/dev/purge-floor-data`**（[`Pos2DevController`](app/Http/Controllers/Pos2/Pos2DevController.php)・`config('app.pos2_debug')` 必須）で当 `shop_id` の **`table_sessions` を全削除**（`orders` / `order_lines` 等は FK CASCADE）し、成功後に `pos2_master_{shop}_*` と `pos_draft_{shop}_*` を削除して Pinia を初期化、[`loadBootstrap`](resources/js/pos2/Pages/Index.vue) を再実行する（[`pos2LocalStorageCleanup.js`](resources/js/pos2/utils/pos2LocalStorageCleanup.js)）。サーバー掃除に失敗した場合は **localStorage は変更しない**。
*   **本番の定期整理:** 旧ドラフトキーが残る環境向けに、**Recu 成功後・会計クローズ後**などドメインイベントにフックして「当該セッションのドラフトキーだけ `removeItem`」する方が安全（即送信化後もキー規約は維持）。全消しはオペ用・ステージング向け。

### 10-11. Add to Table 楽観マージ（B+C）

*   **`useDraftStore.submitLines`:** バリデーション通過後、**同一ティック**で `appendOptimisticStaffSubmit` → `clearDraft` → `isOrderSubmitting = true` → **`void _runOptimisticSubmitBackground`** の順（UI は `await` せず即 `{ ok: true }` を返しうる）。
*   **`usePos2SessionUiStore.appendOptimisticStaffSubmit`:** `axios.post` の**前**に、今回送信する `linesPlain[]` を `sessionOrdersPayload.orders` に仮 1 注文としてマージ（各行 `is_unsent: true`, `ordered_by: staff`）。右ペイン（`SessionRightColumn`）は **確定リストのみ**だが、`sessionOrdersPayload` 経由で **薄赤「KDS前」** の仮行が即表示される。
*   **`useDraftStore._runOptimisticSubmitBackground`:** `void` で POST → 201 時 `patchActiveSessionId`（空卓）→ `fetchSessionOrders(sid, { skipLoadingUi: true })`（失敗時は 1 回リトライ）→ `void fetchTableDashboard`。`applySessionOrdersJson` で仮データごと権威に置換。
*   **ロールバック:** POST 非 201 / ネットワーク失敗時のみ `removeOptimisticStaffSubmit` ＋ **`submitLines` 冒頭で保存した `rollbackLines`（送信直前の `this.lines`、通常は `[]`）を復元** ＋アラート。POST 成功後の GET 失敗では **`lines` を戻さない**（DB 済みのため）。楽観再検証中は読み込みスピナーを出さない（`skipLoadingUi`）。

### 10-12. QR 注文の軽量監視（ダッシュボードポーリング・同卓再タップ）

他卓の QR 注文で左タイルの `uiStatus` が変わっても F5 不要にするため、`Index.vue` マウント中 **`GET /pos2/api/table-dashboard` を約 15 秒間隔**でバックグラウンド実行する（`setInterval`）。**`draftStore.isOrderSubmitting` または Recu 送信中はティックをスキップ**し、失敗はサイレント（アラートなし）。成功トレース／API ログの肥大化を避けるため、定期ティックでは成功時に Trace を積まない。

**同卓再タップ:** 既に選択中の `TableGrid` タイルを再度タップしたとき、`adding` なら **`exitToMonitoring`** のうえ、**`fetchTableDashboard({ silent: true })` を先に実行**し、続けて当該タイルの `activeTableSessionId` に基づき **`fetchSessionOrders`（`silent`・`skipLoadingUi`）**で右ペインを更新する（実装は `applyDashboardDeltaToSelectedTable`）。右上 **`[↻]`** と同様、**タイル（色・件数）と注文リストを同じタイミングで最新化**し、リストだけ先に進んでタイルがポーリング待ちになる認知ズレを防ぐ。

### 10-13. Phase 5 GATEWAY（ブリッジ：既存 Livewire 印刷・会計）

Vue（POS V2）から **決済 DOM を再実装しない**。`GET /pos2/bridge/sessions/{id}/addition|cloture|receipt`（`pos2.auth`）で **最小レイアウト**に既存 **`ReceiptPreview` / `ClotureModal`** を載せ、別タブで開く。**`Pos2BridgeMessenger`** が `receipt-preview-printed` / `close-receipt` / `pos-settlement-completed` を受け **`window.opener.postMessage({ type: 'pos2-bridge', ... })`**。親の `Index.vue` が `message` を受け **`fetchTableDashboard` + `fetchSessionOrders`**。ツールバーの L'ADDITION / CLÔTURE は **常時表示**し、押下時のみ **`sessionTotalMinor === 0` または `has_unacked_placed` で alert 弾き**（通信なし）してから `window.open`。