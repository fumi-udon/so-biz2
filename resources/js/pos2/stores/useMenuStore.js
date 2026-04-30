/**
 * @file useMenuStore.js
 * カテゴリ選択・商品グリッド・ConfigModal の状態を管理するストア。
 *
 * 設計原則:
 *  - 0ms 切り替え: カテゴリ選択時に HTTP 通信なし
 *  - インデックスは bootstrap/マスタ更新時に一度だけ構築
 *  - configModal は「UI選択中」だけを保持（カート行はビルダーが生成）
 */

import { defineStore } from 'pinia';

// ---------------------------------------------------------------------------
// ストア定義
// ---------------------------------------------------------------------------

export const useMenuStore = defineStore('pos2Menu', {
    state: () => ({
        /** 現在選択中のカテゴリID（文字列。null は未選択） */
        activeCategoryId: null,

        /** カテゴリID → 商品IDリスト のインデックス
         * @type {Record<string, string[]>}
         */
        categoryItemIndex: {},

        /** 商品ID → 商品オブジェクト のインデックス
         * @type {Record<string, object>}
         */
        itemIndex: {},

        /** ConfigModal の状態 */
        configModal: {
            open: false,
            /** @type {object|null} 選択中の masterItem */
            masterItem: null,
            /** @type {string|null} 選択中スタイルID */
            selectedStyleId: null,
            /** @type {string[]} 選択中トッピングID配列 */
            selectedToppingIds: [],
        },
    }),

    getters: {
        /** カテゴリ一覧（masterStore から注入、ソート済み想定） */
        visibleItems(state) {
            if (!state.activeCategoryId) return [];
            const ids = state.categoryItemIndex[String(state.activeCategoryId)] ?? [];
            return ids.map((id) => state.itemIndex[id]).filter(Boolean);
        },

        /** ConfigModal 用: 現在の masterItem の options_payload を正規化して返す */
        modalOptionsPayload(state) {
            const item = state.configModal.masterItem;
            if (!item) return { rules: { style_required: false }, styles: [], toppings: [] };
            const payload = item.options_payload;
            if (!payload || typeof payload !== 'object') {
                return { rules: { style_required: false }, styles: [], toppings: [] };
            }
            return {
                rules: {
                    style_required: payload.rules?.style_required === true,
                },
                styles: Array.isArray(payload.styles) ? payload.styles : [],
                toppings: Array.isArray(payload.toppings) ? payload.toppings : [],
            };
        },

        /** 「カートに追加」ボタンの活性判定。ConfigModal で唯一使う computed。 */
        canAddToCart(state) {
            const opts = this.modalOptionsPayload;
            if (opts.rules.style_required) {
                const style = this.selectedStyle;
                if (!style || String(style.id ?? '').trim() === '') {
                    return false;
                }
            }
            return true;
        },

        /** ConfigModal で選択中のスタイルオブジェクト（存在しなければ null）*/
        selectedStyle(state) {
            const id = state.configModal.selectedStyleId;
            if (!id) return null;
            const styles = this.modalOptionsPayload.styles;
            return styles.find((s) => String(s.id) === String(id)) ?? null;
        },

        /** ConfigModal で選択中のトッピングオブジェクト配列 */
        selectedToppings(state) {
            const ids = state.configModal.selectedToppingIds;
            const toppings = this.modalOptionsPayload.toppings;
            return ids.map((id) => toppings.find((t) => String(t.id) === String(id))).filter(Boolean);
        },

        /** ConfigModal で現在選択中の設定での単価 minor（表示用） */
        modalTotalUnitPriceMinor(state) {
            const item = state.configModal.masterItem;
            if (!item) return 0;
            const base = toSafeMinor(item.from_price_minor ?? item.price_minor ?? item.base_price_minor ?? 0);
            const toppingPrice = this.selectedToppings.reduce(
                (s, t) => s + toSafeMinor(t.price_delta_minor ?? t.price_minor ?? 0), 0,
            );
            // スタイル選択時: style.price_minor が確定単価（ベースと足し算しない）。未選択時はベース + トッピング。
            if (this.selectedStyle) {
                return toSafeMinor(this.selectedStyle.price_minor ?? 0) + toppingPrice;
            }
            return base + toppingPrice;
        },

        /** インデックスが構築済みかどうか */
        isIndexBuilt(state) {
            return Object.keys(state.itemIndex).length > 0;
        },
    },

    actions: {
        /**
         * masterStore から受け取った categories + menuItems でインデックスを構築。
         * bootstrap 後に一度だけ呼ぶ。
         *
         * @param {object[]} categories
         * @param {object[]} menuItems
         */
        buildIndex(categories, menuItems) {
            const itemIndex = {};
            for (const item of menuItems) {
                const id = String(item.id ?? '');
                if (!id) continue;
                itemIndex[id] = item;
            }

            const categoryItemIndex = {};
            for (const cat of categories) {
                const catId = String(cat.id ?? '');
                if (!catId) continue;
                categoryItemIndex[catId] = [];
            }

            for (const item of menuItems) {
                const itemId = String(item.id ?? '');
                const catId = String(item.menu_category_id ?? '');
                if (!itemId || !catId) continue;
                if (!categoryItemIndex[catId]) {
                    categoryItemIndex[catId] = [];
                }
                categoryItemIndex[catId].push(itemId);
            }

            this.itemIndex = itemIndex;
            this.categoryItemIndex = categoryItemIndex;

            // デフォルトカテゴリを先頭に設定
            const firstCatId = categories[0]?.id ? String(categories[0].id) : null;
            if (!this.activeCategoryId && firstCatId) {
                this.activeCategoryId = firstCatId;
            }
        },

        /**
         * カテゴリを切り替える（0ms、通信なし）。
         * @param {string|number} categoryId
         */
        selectCategory(categoryId) {
            this.activeCategoryId = String(categoryId);
        },

        /**
         * オプションのある商品のモーダルを開く。
         * @param {object} masterItem
         */
        openConfigModal(masterItem) {
            this.configModal.masterItem = masterItem;
            this.configModal.selectedStyleId = null;
            this.configModal.selectedToppingIds = [];
            this.configModal.open = true;
        },

        /** ConfigModal を閉じる（選択状態もリセット）。 */
        closeConfigModal() {
            this.configModal.open = false;
            this.configModal.masterItem = null;
            this.configModal.selectedStyleId = null;
            this.configModal.selectedToppingIds = [];
        },

        /**
         * スタイルを選択（ラジオ相当）。
         * @param {string|number} styleId
         */
        selectStyle(styleId) {
            this.configModal.selectedStyleId = String(styleId);
        },

        /**
         * トッピングをトグル（チェックボックス相当）。
         * @param {string|number} toppingId
         */
        toggleTopping(toppingId) {
            const id = String(toppingId);
            const idx = this.configModal.selectedToppingIds.indexOf(id);
            if (idx >= 0) {
                this.configModal.selectedToppingIds.splice(idx, 1);
            } else {
                this.configModal.selectedToppingIds.push(id);
            }
        },
    },
});

// ---------------------------------------------------------------------------
// 内部ユーティリティ
// ---------------------------------------------------------------------------

function toSafeMinor(value) {
    const n = Number(value);
    return Number.isFinite(n) ? Math.round(n) : 0;
}
