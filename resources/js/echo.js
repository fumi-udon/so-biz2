import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

const shouldLogToConsole = (() => {
    try {
        const byEnv = String(import.meta.env.VITE_PUSHER_LOG_TO_CONSOLE || '').toLowerCase() === 'true';
        const byQuery = new URL(window.location.href).searchParams.get('pusherDebug') === '1';

        return byEnv || byQuery;
    } catch {
        return String(import.meta.env.VITE_PUSHER_LOG_TO_CONSOLE || '').toLowerCase() === 'true';
    }
})();

if (shouldLogToConsole && window.Pusher) {
    window.Pusher.logToConsole = true;
}

// パブリックチャンネルのみ（Echo.channel）。private / presence は使わず /broadcasting/auth を呼ばない。
// ローカル等で VITE_PUSHER_APP_KEY が無い場合や初期化例外時も後続バンドル（order-store 等）を読み込めるようにする。
const pusherKey = import.meta.env.VITE_PUSHER_APP_KEY;

if (pusherKey) {
    try {
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: pusherKey,
            cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER,
            forceTLS: true,
        });
        window.dispatchEvent(new Event('EchoLoaded'));
    } catch (err) {
        console.warn('[echo] Echo initialization failed; realtime disabled.', err);
        window.Echo = undefined;
    }
} else {
    console.warn('[echo] VITE_PUSHER_APP_KEY is not set; Echo skipped.');
    window.Echo = undefined;
}
