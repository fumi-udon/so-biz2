/**
 * Maps Epson / transport codes to staff-facing Japanese guidance (kitchen counter).
 *
 * @param {string|null|undefined} code
 * @param {string|null|undefined} rawMessage
 * @returns {{ displayCode: string, staffMessage: string }}
 */
export function resolvePosPrinterStaffMessage(code, rawMessage) {
    const c = String(code ?? '').trim();
    const upper = c.toUpperCase();
    const raw = String(rawMessage ?? '');

    if (upper === 'EX_TIMEOUT' || upper.includes('EX_ENPC_TIMEOUT')) {
        return {
            displayCode: 'EX_TIMEOUT',
            staffMessage:
                '【印刷タイムアウト】他のPCやタブレットで印刷画面を開きっぱなしにしていませんか？一度すべてのタブを閉じて、プリンターの電源を入れ直してください。',
        };
    }

    if (upper === 'ERROR_TIMEOUT') {
        if (raw.includes('SSL') || raw.includes('certificate')) {
            return resolveSslMessage();
        }
        return {
            displayCode: 'ERROR_TIMEOUT',
            staffMessage:
                '【接続エラー】プリンターと通信できません。IPアドレスが正しいか、Wi-Fiが切れていないか確認してください。',
        };
    }

    if (upper === 'PAPER_OUT' || upper.includes('EPTR_REC') || (upper.includes('PAPER') && upper.includes('END'))) {
        return {
            displayCode: upper.includes('EPTR') ? upper : 'PAPER_OUT',
            staffMessage: '【用紙切れ】プリンターの紙がなくなっています。新しいロール紙を補充してください。',
        };
    }

    if (
        upper === 'COVER_OPEN' ||
        upper.includes('COVER') ||
        upper.includes('EPTR_COVER') ||
        upper.includes('MECR')
    ) {
        return {
            displayCode: upper.includes('EPTR') ? upper : 'COVER_OPEN',
            staffMessage:
                '【カバーが開いています】プリンターの蓋がしっかり閉まっているか確認してください。',
        };
    }

    if (
        upper === 'SSL_ERROR' ||
        upper.includes('SSL') ||
        raw.includes('SSL') ||
        raw.includes('certificate') ||
        raw.includes('Certificate')
    ) {
        return resolveSslMessage();
    }

    const displayCode = c || 'UNKNOWN';
    return {
        displayCode,
        staffMessage: `【不明なエラー】エラーコード: ${displayCode} を管理者に報告してください。`,
    };
}

function resolveSslMessage() {
    return {
        displayCode: 'SSL_ERROR',
        staffMessage:
            '【セキュリティ許可不足】ブラウザでプリンターへの通信が許可されていません。一度管理画面(https://192.168.1.101:8043)を直接開いて、アクセスを許可してください。',
    };
}
