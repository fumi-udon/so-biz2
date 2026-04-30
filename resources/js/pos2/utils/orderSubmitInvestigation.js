/**
 * 注文送信まわりの調査コード（本ロジックから完全分離）。
 * 失敗しても POS のメイン処理を止めない（pos_v2_architecture §8）。
 *
 * @param {object|null} debugStore - useDebugStore() の戻り値
 * @param {boolean} debugEnabled - page.props.auth.debug（POS2_DEBUG）
 * @param {string} event
 * @param {object} payload
 */
export function pushOrderSubmitTrace(debugStore, debugEnabled, event, payload) {
    if (debugEnabled !== true || !debugStore) {
        return;
    }
    try {
        debugStore.pushTrace(event, payload);
    } catch (e) {
        console.warn('[pos2] order submit trace failed (non-fatal)', e);
    }
}

/**
 * Debug パネル用「直近の注文送信試行」スナップショット（要約のみ、全文は載せない）。
 *
 * @param {object|null} debugStore
 * @param {boolean} debugEnabled
 * @param {object} snapshot
 */
export function recordLastOrderSubmitAudit(debugStore, debugEnabled, snapshot) {
    if (debugEnabled !== true || !debugStore) {
        return;
    }
    try {
        debugStore.recordLastOrderSubmit(snapshot);
    } catch (e) {
        console.warn('[pos2] recordLastOrderSubmitAudit failed (non-fatal)', e);
    }
}
