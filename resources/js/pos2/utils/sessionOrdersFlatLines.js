/**
 * セッション GET …/orders のペイロードを、SessionRightColumn と同一ルールでフラット化する。
 * @param {object|null|undefined} sessionOrdersPayload
 * @returns {object[]}
 */
export function flattenSessionOrderLines(sessionOrdersPayload) {
    const orders = sessionOrdersPayload?.orders;
    if (!Array.isArray(orders)) {
        return [];
    }
    const out = [];
    for (const o of orders) {
        const ob = o?.ordered_by ?? 'staff';
        const lines = o?.lines;
        if (!Array.isArray(lines)) {
            continue;
        }
        for (const ln of lines) {
            out.push({
                ...ln,
                order_id: o.id,
                ordered_by: ln.ordered_by ?? ob,
            });
        }
    }
    return out;
}
