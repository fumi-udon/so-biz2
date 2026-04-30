/**
 * POS2 JSON POST 用の共通ヘッダー（CSRF・Laravel 期待ヘッダ）。
 * @returns {Record<string, string>}
 */
export function buildPos2JsonHeaders() {
    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    };

    const meta = typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        : null;
    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }

    if (typeof document !== 'undefined' && document.cookie) {
        const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
        if (match?.[1]) {
            try {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(match[1]);
            } catch {
                // 調査用ヘッダー失敗は握りつぶす（送信本体は別ヘッダで通る可能性あり）
            }
        }
    }

    return headers;
}
