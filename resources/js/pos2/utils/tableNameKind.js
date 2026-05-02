/**
 * 卓名から種別判定（POS V2 グリッドと整合）。
 */

export function tableNameNormalized(tableOrName) {
    if (tableOrName == null) {
        return '';
    }
    if (typeof tableOrName === 'object' && 'name' in tableOrName) {
        return String(tableOrName.name ?? '').trim();
    }
    return String(tableOrName).trim();
}

export function isTakeoutTableName(tableOrName) {
    return tableNameNormalized(tableOrName).toUpperCase().startsWith('TK');
}
