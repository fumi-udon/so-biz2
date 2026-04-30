# api/

HTTP 通信の単一窓口。`axios` を使うのはこのディレクトリのみ。

## ルール
- `Pages/` や `stores/` から直接 `axios`/`fetch` しない
- エンドポイント関数は Promise を返し、エラーを呼び元に throw する
- CSRF トークンは axios インスタンスに集約（`meta[name=csrf-token]` から取得）

## ファイル例
- `client.js`    — axios インスタンス共通設定
- `tickets.js`   — `/kds2/api/tickets` 関連
- `master.js`    — `/kds2/api/master` 関連
