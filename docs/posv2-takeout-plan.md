# POS V2 テイクアウト客名表示 実装計画

## 背景
テイクアウト注文時にスタッフが
お客様を間違えないよう、
テーブルセルに客名を表示する。

## 実装方針
DBは触らずフロントのみで実現（Phase A）
→ 後からDB保存・KDS2連携を追加（Phase B）

## Phase A（現在対象）
### 概要
Pinia + sessionStorageで客名を管理。
DBは一切触らない。
KDS2は対象外。

### データ設計
- 保存先：sessionStorage
- キー：pos_label_{shopId}_{tableSessionId}
- 値：{ name: '田中様', tel: '0901234567' }
- Cloture完了時に自動クリア

### UI
- TKテーブル選択時に右端固定チップ表示
- チップ押下で名前・TEL入力ポップアップ
- 登録後：セルに「TK1 / 田中様」表示
- 未登録：「TK1」のまま（運用カバー可）

### 変更しないもの
- バックエンド全て
- KDS2
- 既存のtableStore / cartStore

## Phase B（Phase A完了後）
### 概要
DBのtable_sessionsにcustomer_nameを保存。
KDS2のticketsレスポンスに客名を追加。

### 追加作業
1. table_sessionsにcustomer_nameカラム追加
   （migration 1本）
2. POS V2から客名を保存するAPI追加
   （1エンドポイント）
3. KDS2のKdsV2Controller::tickets()修正
   $tableName に customer_name を連結
4. POS V2はサーバー値をPiniaにミラー

### KDS2の表示
登録済み：「TK1 / 田中様」
未登録：「TK1」（現状と同じ）

## キー設計の根拠
restaurant_table_id単独キーは禁止。
理由：客の入替時に前客のデータが
復活する誤請求リスクがある。
必ずtable_session_idを含めること。

## 完了条件（Phase A）
- TKテーブル選択時に浮きチップが出る
- 客名登録後セルに「TK1 / 田中様」表示
- Cloture後に名前がクリアされる
- 他のテーブル操作に影響しない
- npm run build 成功

## 完了条件（Phase B）
- POS V2から客名がDBに保存される
- KDS2のチケットに客名が表示される
- 前客の名前が次客に引き継がれない
- php -l 成功
- npm run build 成功