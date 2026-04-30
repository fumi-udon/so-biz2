# lib/

純ロジック置き場（Vue / Pinia / DOM 依存なし）。

## 用途例
- `queue.js`          — pending_actions のリトライロジック
- `idempotency.js`    — 二重タップ防止（同一 idempotency キーのマージ）
- `batchKey.js`       — バッチキー計算（`b:uuid` / `o:orderId`）
- `clock.js`          — サーバー時刻オフセット補正
