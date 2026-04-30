# KDS V2 実装仕様書
最終更新: 2026-04-30

---

## 1. 開発方針

| 項目 | 内容 |
|---|---|
| ディレクトリ | `resources/js/kds2/` で完全独立管理 |
| スタック | Laravel 11 + Vue 3 (Composition API) + Pinia + TailwindCSS |
| Inertia.js | **使用しない**（KDSは単一画面、OVH共有ホスティングでSSR不要） |
| 構成 | `/kds2` → Bladeシェル1枚 → Vue が APIを叩く |
| 動作環境 | OVH共有ホスティング（Node.js不要、ビルド済みJS配信） |
| 既存への影響 | 既存KDS(Livewire版)・POS・会計ロジックに一切触れない |
| 並行稼働 | `/kds2` で稼働確認後、安定したら `/kds` と切り替え |

---

## 2. オフラインファースト設計（4つの柱）

### ① マスタの完全LocalStorage化
- **対象**: カテゴリ・Kitchen/Hall設定
- **同期**: 手動「マスタ同期ボタン」（1日2回の運用ルール）
- **効果**: 通信量99%削減・フィルタ切り替え0ms

### ② 完全楽観的UI & バックグラウンドキュー
```
タップ
 └→ Pinia即時更新（0ms）→ 画面グレーアウト
 └→ pending_actions キューに追加
     └→ バックグラウンドでAPIへ送信
         ├→ 成功: キューから削除
         └→ 失敗: リトライ（最大5回）→ 全失敗時はロールバック
```

### ③ Pusherはシグナルのみ
- payload極小: `{"action":"served","ticket_id":892}`
- 3台のタブレット間でAPIを叩かずにPinia状態を直接同期
- Pusher障害時はスマートポーリングが自動補完

### ④ スマートポーリング
- WebSocket接続中: ポーリング停止
- WebSocket切断時: Vue側で2〜5秒間隔の差分取得を自動起動

---

## 3. 現場運用ロジック

| ロジック | 仕様 |
|---|---|
| 最後の1品 | `is_last: true` のチケットをServedにする際のみ確認モーダル → OK で列消去 |
| フィルタ | Kitchen/HallチェックBOX → useFilterStore経由でPiniaが0msフィルタリング |
| 冪等性 | 複数端末の同時タップ → 「Served収束」として扱い、エラーを出さない |
| カテゴリ未設定 | filterStrict=false の場合は全チケット表示（警告バナーなし） |

---

## 4. APIエンドポイント

### GET /kds2/api/tickets
```json
{
  "shop_id": 3,
  "batches": [
    {
      "key": "b:uuid-xxx",
      "table": "T3",
      "tickets": [
        {
          "id": 123,
          "rev": 2,
          "name": "ラーメン",
          "qty": 1,
          "status": "confirmed",
          "cat_id": 2,
          "is_last": false
        },
        {
          "id": 124,
          "rev": 1,
          "name": "餃子",
          "qty": 2,
          "status": "confirmed",
          "cat_id": 3,
          "is_last": true
        }
      ]
    }
  ],
  "queued": 0,
  "generated_at": "2026-04-30T10:00:00+01:00"
}
```
- `is_last: true` = バッチ内の残りConfirmed/Cookingが自分1件 → 確認モーダルのトリガー
- `queued` = 画面外の待機列数（3列を超えたバッチ数）
- サーバー側フィルタなし（Kitchen/Hallフィルタはクライアント側で処理）

### POST /kds2/api/tickets/{id}/served
- body: `{"rev": 5}`
- 冪等: 既にServedなら200を返す（エラーにしない）
- 楽観的UI済みのため、レスポンスでの画面更新は不要

### GET /kds2/api/master
```json
{
  "categories": [
    {"id": 1, "name": "麺類", "sort_order": 1},
    {"id": 2, "name": "ご飯", "sort_order": 2}
  ],
  "kitchen_category_ids": [1, 2, 3, 4, 5, 8],
  "hall_category_ids": [6, 7, 8],
  "filter_strict": true
}
```
- Cache::remember 5分
- 認証: 既存 `KdsAuthenticate` middleware流用

---

## 5. ファイル構成

```
app/Http/Controllers/Kds/
└── KdsV2Controller.php

resources/views/kds2/
└── app.blade.php                    # Bladeシェル（Vite + mountポイントのみ）

resources/js/kds2/
├── main.js                          # Vue + Pinia 初期化・mount
├── App.vue                          # ルート: APIポーリング・handleServe
├── stores/
│   ├── useMasterStore.js            # カテゴリ・Kitchen/Hall設定（LocalStorage）
│   ├── useFilterStore.js            # showKitchen/showHall（LocalStorage永続）
│   └── useTicketStore.js            # バッチ・チケット状態・楽観的UI・キュー
└── components/
    ├── KdsBoard.vue                 # 3列グリッド + 待機バッジ
    ├── KdsBatchColumn.vue           # 1列: テーブル名 + チケット一覧
    ├── KdsTicketRow.vue             # 1チケット: タップでServed
    └── KdsFilterBar.vue             # Kitchen/Hallトグル
```

### LocalStorageキー

| キー | 内容 | 更新タイミング |
|---|---|---|
| `kds2_master_{shopId}` | カテゴリ + Kitchen/Hall設定 | 手動同期ボタン |
| `kds2_filter_{shopId}` | showKitchen / showHall | トグル操作毎 |
| `kds2_pending` | 未送信Served操作キュー | 送信完了で削除 |

---

## 6. コンポーネント責務

| コンポーネント | 責務 |
|---|---|
| `App.vue` | APIポーリング(2秒)・handleServe・全体組み立て |
| `KdsBoard.vue` | 3列グリッド表示・待機バッジ・KdsFilterBar配置 |
| `KdsBatchColumn.vue` | 1バッチ列・チケットのservedソート |
| `KdsTicketRow.vue` | 1チケット表示・タップイベント送出 |
| `KdsFilterBar.vue` | Kitchen/Hallトグル・useFilterStore操作 |

---

## 7. 実装フェーズと進捗

### ✅ Phase 1: 環境準備 & 骨組み（完了）
- KdsV2Controller / ルート / Bladeシェル / main.js / App.vue / vite設定

### ✅ Phase 2: オフラインファースト基盤（完了）
- useMasterStore / useFilterStore / useTicketStore
- 動作確認: shopId=3, kitchenIds=[5,1,2,3,4,8], hallIds=[6,7,8]

### 🔄 Phase 3: UI構築 & 楽観的更新（進行中）
- [x] KdsTicketRow.vue
- [x] KdsBatchColumn.vue
- [x] KdsFilterBar.vue
- [x] KdsBoard.vue
- [ ] App.vue更新（APIポーリング + handleServe統合）
- [ ] 実機動作確認

### 🔲 Phase 4: 裏側同期 & Pusher連携
- [ ] バックグラウンドAPIキュー（リトライ5回・失敗時ロールバック）
- [ ] Pusherシグナル受信 → Pinia即時反映
- [ ] WebSocket切断時のスマートポーリング自動切り替え
- [ ] 既存KDS(/kds)との切り替えテスト・本番投入

---

## 8. 既知の問題・注意事項

| 項目 | 内容 |
|---|---|
| ARM64ビルド問題 | `npm run dev` 失敗時: `rm -rf node_modules package-lock.json && npm install` |
| viteプラグイン順序 | `vue()` を `tailwindcss()` より前に配置（確定済み） |
| TailwindとVue | `resources/css/app.css` に `@source '../**/*.vue'` 追加済み |
| KDS認証 | `/kds2` は `kds.auth` middleware。先に `/kds` でPINログインが必要 |
| markServed冪等性 | `UpdateOrderLineStatusAction` 内の `lockForUpdate` + `line_revision` が安全弁 |
| queued値 | Phase 3完了時点ではクライアント管理不要。サーバーが返す値をそのまま表示 |
