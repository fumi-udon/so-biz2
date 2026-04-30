/**
 * @file currency.js
 * チュニジアディナール表示ユーティリティ。
 *
 * 通貨単位:
 *   1 DT = 1000 minor (millimes)
 *   表示は小数点第1位まで（1000 millime 未満の部分は 100 millime 単位で表示）
 *
 * 例:
 *   formatDT(1000)  => "1 DT"
 *   formatDT(10500) => "10.5 DT"
 *   formatDT(80300) => "80.3 DT"
 *   formatDT(0)     => "0 DT"
 */

/**
 * minor 値をチュニジアディナール文字列にフォーマット。
 * 小数点以下の表示は 100 millime 単位（第1位）まで。
 *
 * @param {number} minor - millime 単位の整数
 * @returns {string}
 */
export function formatDT(minor) {
    const n = typeof minor === 'number' && Number.isFinite(minor) ? Math.round(minor) : 0;
    const whole = Math.floor(n / 1000);
    const remainder = n % 1000;
    // 100 millime 単位（第1位）
    const decimal = Math.floor(remainder / 100);

    if (decimal === 0) {
        return `${whole} DT`;
    }
    return `${whole}.${decimal} DT`;
}

/**
 * minor 値をコンパクトな数値文字列にフォーマット（DT 表記なし）。
 * 主に小計や比較用。
 *
 * @param {number} minor
 * @returns {string}
 */
export function formatDTCompact(minor) {
    return formatDT(minor).replace(' DT', '');
}
