/**
 * @file cartLineBuilder.js
 * カートライン（ドラフト行）のスナップショットを生成する唯一の関所。
 *
 * ルール:
 *  - カートに追加する行はすべてこのファイルの buildCartLineSnapshot を経由する
 *  - 金額はすべて整数 minor 単位（1 DT = 1000 minor）
 *  - 一度生成した行は不変（修正は削除→再追加）
 *
 * 単価合計（total_unit_price_minor）の仕様:
 *  - スタイル選択あり: `style.price_minor` はそのスタイルの確定単価（ベース `from_price_minor` と足し算しない）
 *  - スタイル選択なし: `from_price_minor` + トッピング delta 合計
 *  - トッピングは常に delta 加算（スナップショット内は price_minor に正規化）
 */

import { generateUUID } from './uuid.js';

// ---------------------------------------------------------------------------
// JSDoc 型定義
// ---------------------------------------------------------------------------

/**
 * @typedef {Object} OptionSnapshot
 * @property {string} id       - スタイルID（文字列に統一）
 * @property {string} name     - 表示名
 * @property {number} price_minor - 価格 minor（整数）
 * @property {string} [ui_hint]   - UI装飾ヒント（"bold" 等、省略可）
 */

/**
 * @typedef {Object} ToppingSnapshot
 * @property {string} id          - トッピングID（文字列に統一）
 * @property {string} name        - 表示名
 * @property {number} price_minor - 価格 minor（整数）
 * @property {string} [category_tag] - カテゴリタグ（省略可）
 */

/**
 * @typedef {Object} CartLineSnapshot
 * @property {string} cart_item_id         - UUIDv4（行の一意識別子）
 * @property {string} product_id           - 商品ID（文字列）
 * @property {string} name                 - 商品名
 * @property {number} base_price_minor     - カタログの `from_price_minor` を焼いた値（監査・表示用。スタイル選択時も単価計算には使わない）
 * @property {number} qty                  - 数量（>= 1）
 * @property {OptionSnapshot|null} selected_option_snapshot - 選択スタイル（なければ null）
 * @property {ToppingSnapshot[]}  topping_snapshots         - 選択トッピング（なければ []）
 * @property {number} total_unit_price_minor - 単価合計 minor（整数、qty 未乗算）
 * @property {string} display_full_name      - 命綱テキスト（KDS・レシート・ログ用）
 * @property {string} master_generated_at    - マスタ生成日時（行生成時のマスタ世代追跡用）
 * @property {string} added_at               - 行追加日時 ISO文字列
 * @property {string} merge_key             - 同一構成マージ用キー（product|style|sortedToppingIds）
 */

// ---------------------------------------------------------------------------
// ビルダー
// ---------------------------------------------------------------------------

/**
 * CartLineSnapshot を生成する唯一のエントリーポイント。
 *
 * @param {object} params
 * @param {object} params.masterItem           - useMasterStore の menuItems エントリー
 * @param {OptionSnapshot|null} params.selectedOption  - 選択したスタイル（なければ null）
 * @param {ToppingSnapshot[]}  params.selectedToppings - 選択したトッピング配列
 * @param {number}  params.qty                - 数量（デフォルト 1）
 * @param {string}  params.masterGeneratedAt  - masterStore.generatedAt
 * @returns {CartLineSnapshot}
 */
export function buildCartLineSnapshot({
    masterItem,
    selectedOption = null,
    selectedToppings = [],
    qty = 1,
    masterGeneratedAt = '',
}) {
    const productId = String(masterItem.id ?? '');
    const name = String(masterItem.name ?? '');
    const basePriceMinor = toSafeMinor(masterItem.from_price_minor ?? masterItem.price_minor ?? masterItem.base_price_minor ?? 0);
    const safeQty = Math.max(1, Math.floor(Number(qty) || 1));

    // スタイル
    const optionSnapshot = selectedOption
        ? {
            id: String(selectedOption.id ?? ''),
            name: String(selectedOption.name ?? ''),
            price_minor: toSafeMinor(selectedOption.price_minor ?? 0),
            ui_hint: String(selectedOption.ui_hint ?? ''),
        }
        : null;

    // トッピング
    // API は price_delta_minor を返す。スナップショット内は price_minor に正規化して統一。
    const toppingSnapshots = Array.isArray(selectedToppings)
        ? selectedToppings.map((t) => ({
            id: String(t.id ?? ''),
            name: String(t.name ?? ''),
            price_minor: toSafeMinor(t.price_delta_minor ?? t.price_minor ?? 0),
            category_tag: String(t.category_tag ?? ''),
        }))
        : [];

    // 単価合計（qty 未乗算）
    const toppingPrice = toppingSnapshots.reduce((s, t) => s + t.price_minor, 0);
    const totalUnitPriceMinor = optionSnapshot
        ? (optionSnapshot.price_minor + toppingPrice)
        : (basePriceMinor + toppingPrice);

    // 命綱テキスト
    const displayFullName = buildDisplayFullName(name, optionSnapshot, toppingSnapshots);

    const mergeKey = computeMergeKey(productId, optionSnapshot, toppingSnapshots);

    /** @type {CartLineSnapshot} */
    const line = {
        cart_item_id: generateUUID(),
        product_id: productId,
        name,
        base_price_minor: basePriceMinor,
        qty: safeQty,
        selected_option_snapshot: optionSnapshot,
        topping_snapshots: toppingSnapshots,
        total_unit_price_minor: totalUnitPriceMinor,
        display_full_name: displayFullName,
        master_generated_at: String(masterGeneratedAt ?? ''),
        added_at: new Date().toISOString(),
        merge_key: mergeKey,
    };

    return line;
}

/**
 * 同一商品・同一スタイル・同一トッピング集合のマージ用キー。
 * トッピング ID はソートして順序依存を排除する。
 *
 * @param {string} productId
 * @param {OptionSnapshot|null} optionSnapshot
 * @param {ToppingSnapshot[]} toppingSnapshots
 * @returns {string}
 */
export function computeMergeKey(productId, optionSnapshot, toppingSnapshots) {
    const pid = String(productId ?? '');
    const sid = optionSnapshot?.id != null && String(optionSnapshot.id).length > 0
        ? String(optionSnapshot.id)
        : '';
    const tids = (Array.isArray(toppingSnapshots) ? toppingSnapshots : [])
        .map((t) => String(t?.id ?? ''))
        .filter((id) => id.length > 0)
        .sort();
    return `${pid}|${sid}|${tids.join(',')}`;
}

// ---------------------------------------------------------------------------
// ランタイムアサート（POS2_DEBUG 時のみ）
// ---------------------------------------------------------------------------

/** 必須キー一覧と型チェックルール */
const REQUIRED_KEYS = [
    { key: 'cart_item_id', check: (v) => typeof v === 'string' && v.length > 0 },
    { key: 'product_id',   check: (v) => typeof v === 'string' && v.length > 0 },
    { key: 'name',         check: (v) => typeof v === 'string' },
    { key: 'base_price_minor',       check: (v) => Number.isInteger(v) && v >= 0 },
    { key: 'qty',                    check: (v) => Number.isInteger(v) && v >= 1 },
    { key: 'total_unit_price_minor', check: (v) => Number.isInteger(v) && v >= 0 },
    { key: 'display_full_name',      check: (v) => typeof v === 'string' && v.length > 0 },
    { key: 'topping_snapshots',      check: (v) => Array.isArray(v) },
    { key: 'added_at',               check: (v) => typeof v === 'string' && v.length > 0 },
    { key: 'merge_key',              check: (v) => typeof v === 'string' && v.length > 0 },
];

/**
 * デバッグ時のみ呼ばれるランタイムアサート。
 * 失敗してもカート投入を止めない（調査コードは本ロジックを止めない原則）。
 *
 * @param {CartLineSnapshot} line
 * @param {boolean} debugEnabled
 * @returns {{ ok: boolean, errors: string[] }}
 */
export function assertCartLine(line, debugEnabled = false) {
    if (!debugEnabled) return { ok: true, errors: [] };

    const errors = [];

    if (!line || typeof line !== 'object') {
        return { ok: false, errors: ['line is not an object'] };
    }

    for (const { key, check } of REQUIRED_KEYS) {
        if (!(key in line)) {
            errors.push(`missing key: ${key}`);
        } else if (!check(line[key])) {
            errors.push(`invalid value for ${key}: ${JSON.stringify(line[key])}`);
        }
    }

    // selected_option_snapshot が null でなければ内部も検証
    if (line.selected_option_snapshot !== null && line.selected_option_snapshot !== undefined) {
        const o = line.selected_option_snapshot;
        if (typeof o !== 'object') {
            errors.push('selected_option_snapshot is not an object');
        } else {
            if (typeof o.id !== 'string') errors.push('selected_option_snapshot.id must be string');
            if (typeof o.name !== 'string') errors.push('selected_option_snapshot.name must be string');
            if (!Number.isInteger(o.price_minor)) errors.push('selected_option_snapshot.price_minor must be integer');
        }
    }

    // topping_snapshots 内部検証
    if (Array.isArray(line.topping_snapshots)) {
        line.topping_snapshots.forEach((t, i) => {
            if (typeof t.id !== 'string') errors.push(`topping_snapshots[${i}].id must be string`);
            if (typeof t.name !== 'string') errors.push(`topping_snapshots[${i}].name must be string`);
            if (!Number.isInteger(t.price_minor)) errors.push(`topping_snapshots[${i}].price_minor must be integer`);
        });
    }

    return { ok: errors.length === 0, errors };
}

// ---------------------------------------------------------------------------
// 内部ユーティリティ
// ---------------------------------------------------------------------------

/**
 * 安全な minor 変換。浮動小数・文字列でも整数に正規化する。
 * @param {number|string} value
 * @returns {number}
 */
function toSafeMinor(value) {
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : 0;
}

/**
 * 命綱テキストを生成。
 * 例: "醤油ラーメン (大盛り) + スパイシー, チーズ"
 *
 * @param {string} baseName
 * @param {OptionSnapshot|null} option
 * @param {ToppingSnapshot[]} toppings
 * @returns {string}
 */
function buildDisplayFullName(baseName, option, toppings) {
    let result = baseName;
    if (option?.name) {
        result += ` (${option.name})`;
    }
    if (toppings.length > 0) {
        result += ` + ${toppings.map((t) => t.name).join(', ')}`;
    }
    return result;
}
