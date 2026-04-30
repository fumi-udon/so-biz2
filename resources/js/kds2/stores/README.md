# stores/

Pinia ストア置き場。KDS V2 の Single Source of Truth。

## ルール（.cursorrules [RULE] KDS V2 SPA Architecture）
- ユーザー操作 → **必ずここを通す**（Page コンポーネントから直接 axios 禁止）
- 楽観的更新: store を即時更新 → `pending_actions` キューへ積む → バックグラウンド送信
- Pusher イベントハンドラも store への patch のみ（fetch 再取得禁止）

## 命名規則
- `use{Domain}Store.js` 形式 (例: `useTicketStore.js`, `useMasterStore.js`)
