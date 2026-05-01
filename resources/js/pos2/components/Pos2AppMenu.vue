<script setup>
/**
 * POS2 開発用ハンバーメニュー（POS2_DEBUG / auth.debug のみ表示）。
 * localStorage の POS2 管轄キーのみ削除し、Pinia を安全に初期化する。
 */
import { onUnmounted, ref, watch } from 'vue';
import { collectPos2StorageKeysForShop, runPos2ClientStoragePurge } from '../utils/pos2LocalStorageCleanup';
import { buildPos2JsonHeaders } from '../utils/pos2Http';

const props = defineProps({
    shopId: {
        type: Number,
        required: true,
    },
});

const emit = defineEmits(['purged']);

const open = ref(false);

function close() {
    open.value = false;
}

watch(open, (v) => {
    if (typeof document === 'undefined') return;
    document.body.classList.toggle('overflow-hidden', v);
});

onUnmounted(() => {
    if (typeof document !== 'undefined') {
        document.body.classList.remove('overflow-hidden');
    }
});

function onBackdropClick() {
    close();
}

async function onDevCleanUp() {
    const sid = Number(props.shopId);
    if (!Number.isFinite(sid) || sid < 1) {
        window.alert('shop_id が無効です。');
        return;
    }
    const keys = collectPos2StorageKeysForShop(sid);
    const sample = keys.slice(0, 6).join('\n');
    const more = keys.length > 6 ? `\n… 他 ${keys.length - 6} 件` : '';
    const ok = window.confirm(
        '【開発専用】DB の卓セッション・注文（全卓 commande 0）を削除し、続けて POS2 の localStorage を消します。\n'
        + '[DEV ONLY] Supprime sessions/commandes en base puis le cache local POS2.\n\n'
        + `localStorage 対象キー数: ${keys.length}\n`
        + `${sample || '(該当キーなし)'}${more}\n\n`
        + '続行しますか？ / Continuer ?',
    );
    if (!ok) {
        return;
    }
    try {
        const res = await fetch('/pos2/api/dev/purge-floor-data', {
            method: 'POST',
            credentials: 'same-origin',
            headers: buildPos2JsonHeaders(),
            body: JSON.stringify({}),
        });
        const body = await res.json().catch(() => ({}));
        if (!res.ok) {
            const msg = typeof body?.message === 'string' && body.message.trim() !== ''
                ? body.message.trim()
                : `HTTP ${res.status}`;
            window.alert(
                `サーバー側の掃除に失敗しました（localStorage は変更していません）。\n${msg}\n\n`
                + 'Échec côté serveur (localStorage intact).',
            );
            return;
        }

        runPos2ClientStoragePurge(sid);
        close();
        emit('purged');
    } catch (e) {
        window.alert(
            `掃除中にエラーが発生しました: ${e instanceof Error ? e.message : 'unknown'}\n`
            + 'Erreur pendant le nettoyage.',
        );
    }
}
</script>

<template>
    <div class="relative inline-block">
        <button
            type="button"
            class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-600 bg-slate-900/95 text-slate-200 shadow-lg backdrop-blur-sm transition hover:border-slate-500 hover:bg-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-500 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 dark:border-slate-500 dark:bg-slate-900 dark:text-slate-100"
            :aria-expanded="open"
            aria-controls="pos2-dev-menu-panel"
            aria-label="開発メニュー"
            @click="open = !open"
        >
            <span class="sr-only">メニュー</span>
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>

        <Teleport to="body">
            <div
                v-if="open"
                id="pos2-dev-menu-panel"
                class="pointer-events-auto fixed inset-0 z-[44] flex justify-end bg-black/50 backdrop-blur-[2px] dark:bg-black/60"
                role="dialog"
                aria-modal="true"
                aria-labelledby="pos2-dev-menu-title"
                @click.self="onBackdropClick"
            >
                <div
                    class="flex h-full w-[min(100%,20rem)] flex-col border-l border-slate-700 bg-slate-950 text-slate-100 shadow-2xl dark:bg-slate-950 dark:text-slate-100"
                    @click.stop
                >
                    <div class="flex items-center justify-between border-b border-slate-700 px-4 py-3">
                        <h2 id="pos2-dev-menu-title" class="text-sm font-bold tracking-wide text-cyan-300 dark:text-cyan-200">
                            Dev
                        </h2>
                        <button
                            type="button"
                            class="rounded-lg px-2 py-1 text-xs text-slate-400 hover:bg-slate-800 hover:text-slate-200 dark:text-slate-400 dark:hover:text-slate-200"
                            @click="close"
                        >
                            閉じる
                        </button>
                    </div>
                    <div class="flex-1 space-y-4 overflow-y-auto px-4 py-4">
                        <p class="text-xs leading-relaxed text-slate-400 dark:text-slate-400">
                            先に <code class="rounded bg-slate-800 px-1 text-[10px] text-cyan-200">POST /pos2/api/dev/purge-floor-data</code> で当店の <strong class="text-slate-300">table_sessions</strong>（紐づく注文は CASCADE）を削除し、続けてマスタキャッシュ（<code class="rounded bg-slate-800 px-1 text-[10px] text-cyan-200">pos2_master_*</code>）と卓ドラフト（<code class="rounded bg-slate-800 px-1 text-[10px] text-cyan-200">pos_draft_*</code>）を削除します。要 <code class="text-[10px] text-cyan-200">POS2_DEBUG=true</code>。
                        </p>
                        <button
                            type="button"
                            class="w-full rounded-xl border-2 border-rose-700 bg-rose-950/50 py-3 text-sm font-bold text-rose-100 transition hover:bg-rose-900/60 dark:border-rose-600 dark:text-rose-50"
                            @click="onDevCleanUp"
                        >
                            Dev clean up
                        </button>
                        <p class="text-[11px] leading-snug text-slate-500 dark:text-slate-500">
                            本番の定期掃除は、未送信ドラフトを失わないよう Recu 後バッチやオペ手順と設計すること（§10 ドキュメント参照）。
                        </p>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
