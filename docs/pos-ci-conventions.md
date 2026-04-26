# POS Phase 2-A — CI grep 規約

Livewire POS 卓周りで、ドラフト誤用と `effects.html` 依存を防ぐための **grep ベース**チェック。CI（GitHub Actions / ローカル pre-push 等）に組み込む想定。

## 1. `pos-draft-store.js` — 禁止トークン

**意図:** Alpine `posDraft` ストアに「行の追加・削除・永続キュー・汎用 mutate」系 API を置かない（Phase 2 防護壁）。残像は `writeAfterimageFromAuthoritative` のみ。

**パターン（いずれもマッチ 0 件であること）:**

```text
addItem|removeItem|_persist|queue|mutate
```

**対象ファイル:** `resources/js/pos-draft-store.js`

**実行例（マッチしたら失敗 = grep の終了コード 0 を弾く）:**

```bash
if grep -qE 'addItem|removeItem|_persist|queue|mutate' resources/js/pos-draft-store.js; then
  echo "FAIL: forbidden tokens in pos-draft-store.js"
  exit 1
fi
```

**ripgrep 利用時:**

```bash
if rg -q 'addItem|removeItem|_persist|queue|mutate' resources/js/pos-draft-store.js; then
  echo "FAIL: forbidden tokens in pos-draft-store.js"
  exit 1
fi
```

**期待結果:** 上記スクリプトが **exit 0**（禁止語がファイル内に存在しない）。

**補足:** `sessionStorage` の削除は `storage['remove'+'Item']` のように分割しており、`_persist` や連続する `removeItem` 文字列はソースに含めない。

---

## 2. Blade — `effects.html` 参照の禁止（POS スコープ）

**意図:** Livewire の `effects.html` をパースして行データを復元する方式は採用しない（読取専用残像は `pos-action-host-authoritative` + `posDraft.readAfterimage`）。

**パターン:**

```text
effects\.html
```

**対象スコープ（POS 関連 Blade のみ）:**

- `resources/views/livewire/pos/`
- `resources/views/components/pos-speed-panel.blade.php`
- `resources/views/components/pos/`（例: `footer-utility-menu.blade.php`）

**実行例（マッチしたら失敗）:**

```bash
if rg -q 'effects\.html' \
  resources/views/livewire/pos \
  resources/views/components/pos-speed-panel.blade.php \
  resources/views/components/pos \
  --glob '*.blade.php'
then
  echo "FAIL: effects.html referenced under POS-scoped blades"
  exit 1
fi
```

**grep のみの環境:**

```bash
POS_BLADES="resources/views/livewire/pos resources/views/components/pos-speed-panel.blade.php resources/views/components/pos"
if grep -rE --include='*.blade.php' 'effects\.html' $POS_BLADES; then
  echo "FAIL: effects.html under POS-scoped blades"
  exit 1
fi
```

**期待結果:** 上記が **exit 0**（POS スコープに `effects.html` 文字列が無い）。

**補足:** 開発用の `resources/views/components/livewire-payload-monitor.blade.php` は **上記スコープ外**（デバッグ用途のため `effects.html` 参照を許容）。POS キオスク配下に同パターンを持ち込まないこと。

---

## 3. 推奨 CI ジョブへの組み込み

1. `composer test`（または `php artisan test`）の前後どちらでも可として上記 2 本の grep を実行し、非ゼロマッチで `exit 1`。
2. `resources/js/pos-draft-store.js` はフロントビルド前の lint ステップに含めてもよい。

---

## 4. 関連実装

- `pos-draft-store.js`: `readAfterimage` / `writeAfterimageFromAuthoritative` / LRU
- `TableActionHost.php`: `dispatchAuthoritativeLinePayload()`（`pos-action-host-authoritative`）
- `table-action-host.blade.php`: `lineSurfaceMode` / Hit–Miss–live

## 5. フィールド確認チェックリスト

手動検証は `docs/pos-phase2a-field-checklist.md` を参照。
