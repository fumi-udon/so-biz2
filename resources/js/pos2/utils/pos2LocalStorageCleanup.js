import { useMasterStore } from '../stores/useMasterStore';
import { useDraftStore } from '../stores/useDraftStore';
import { useTableStore } from '../stores/useTableStore';
import { usePos2SessionUiStore } from '../stores/usePos2SessionUiStore';
import { useMenuStore } from '../stores/useMenuStore';
import { useDebugStore } from '../stores/useDebugStore';

/**
 * POS V2 が管理する localStorage キーのみを列挙（他オリジンのデータは触らない）。
 *
 * @see useMasterStore storageKey
 * @see useDraftStore draftKey
 * @param {number} shopId
 * @returns {string[]}
 */
export function collectPos2StorageKeysForShop(shopId) {
    const n = Number(shopId);
    if (!Number.isFinite(n) || n < 1) {
        return [];
    }
    const prefixMaster = `pos2_master_${n}_`;
    const prefixDraft = `pos_draft_${n}_`;
    const out = [];
    if (typeof localStorage === 'undefined') {
        return out;
    }
    for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i);
        if (!k) {
            continue;
        }
        if (k.startsWith(prefixMaster) || k.startsWith(prefixDraft)) {
            out.push(k);
        }
    }
    return [...new Set(out)];
}

/**
 * キー削除のみ（Pinia は呼び出し側でリセット）。
 * @param {string[]} keys
 * @returns {number} 削除試行数
 */
export function removeLocalStorageKeys(keys) {
    let n = 0;
    for (const k of keys) {
        try {
            localStorage.removeItem(k);
            n += 1;
        } catch {
            // クォータ・プライベートモード等でも本処理を止めない
        }
    }
    return n;
}

/**
 * POS2 管轄の localStorage を削除し、関連 Pinia を初期状態へ戻す（1 回の操作で整合）。
 *
 * @param {number} shopId
 * @returns {{ removedKeyCount: number, keys: string[] }}
 */
export function runPos2ClientStoragePurge(shopId) {
    const keys = collectPos2StorageKeysForShop(shopId);
    removeLocalStorageKeys(keys);

    const master = useMasterStore();
    master.clearStorage(shopId);

    const draft = useDraftStore();
    draft.resetAfterLocalStoragePurge(shopId);

    const table = useTableStore();
    table.clearSelection({});

    const sessionUi = usePos2SessionUiStore();
    sessionUi.$reset();

    const menu = useMenuStore();
    menu.closeConfigModal();

    try {
        useDebugStore().resetDiagnosticsAfterStoragePurge();
    } catch {
        // 調査ストアの失敗は本掃除を止めない
    }

    return { removedKeyCount: keys.length, keys };
}
