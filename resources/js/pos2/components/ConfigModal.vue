<script setup>
/**
 * ConfigModal.vue
 * style / topping 選択モーダル。
 *
 * Guard 設計:
 *   1. computed canAddToCart（useMenuStore から）が false のとき
 *      確定ボタンを :disabled で物理的に無効化。
 *   2. @click ハンドラ先頭でも if(!canAddToCart) return の二重ガード。
 *   3. 投入成功・ブロック時ともに trace に詳細を記録。
 *   4. 確定時は submitLines（1 行即送信）。成功時のみモーダルを閉じる。
 */
import { computed, ref, watch } from 'vue';
import { useMenuStore } from '../stores/useMenuStore';
import { useDraftStore } from '../stores/useDraftStore';
import { useDebugStore } from '../stores/useDebugStore';
import { buildCartLineSnapshot } from '../utils/cartLineBuilder';
import { formatDT } from '../utils/currency';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    masterGeneratedAt: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['close', 'added']);

const page = usePage();
const menuStore = useMenuStore();
const draftStore = useDraftStore();
const debugStore = useDebugStore();

const debugEnabled = computed(() => page.props?.auth?.debug === true);

/** 送信の二重タップ（チャタリング）防止用物理ロック */
const isSubmitting = ref(false);

/** 成功時は finally で即解除しない（閉じる前の 2 本目 click を防ぐ）。モーダルが閉じたら解除。 */
watch(
    () => menuStore.configModal.open,
    (open) => {
        if (!open) isSubmitting.value = false;
    },
);

// ---------------------------------------------------------------------------
// 表示データ（menuStore から読み取るだけ、computed なし）
// ---------------------------------------------------------------------------

const item = computed(() => menuStore.configModal.masterItem);
const opts = computed(() => menuStore.modalOptionsPayload);
const canAddToCart = computed(() => menuStore.canAddToCart);
const selectedStyleId = computed(() => menuStore.configModal.selectedStyleId);
const selectedToppingIds = computed(() => menuStore.configModal.selectedToppingIds);
const previewPrice = computed(() => formatDT(menuStore.modalTotalUnitPriceMinor));

const addButtonEnabled = computed(
    () => canAddToCart.value && !isSubmitting.value && !draftStore.isOrderSubmitting,
);

// ---------------------------------------------------------------------------
// アクション
// ---------------------------------------------------------------------------

function selectStyle(styleId) {
    menuStore.selectStyle(styleId);
}

function toggleTopping(toppingId) {
    menuStore.toggleTopping(toppingId);
}

function isToppingSelected(toppingId) {
    return selectedToppingIds.value.includes(String(toppingId));
}

async function onAddToCart() {
    if (!canAddToCart.value) {
        if (debugEnabled.value) {
            debugStore.pushTrace('cart.add.blocked', {
                reason: 'required_style_not_selected',
                product_id: String(item.value?.id ?? ''),
                name: item.value?.name ?? '',
            });
        }
        return;
    }

    if (isSubmitting.value || draftStore.isOrderSubmitting) {
        return;
    }

    isSubmitting.value = true;

    const traceId = debugEnabled.value ? debugStore.nextTraceId('order-submit-line') : null;

    const cartLine = buildCartLineSnapshot({
        masterItem: item.value,
        selectedOption: menuStore.selectedStyle,
        selectedToppings: menuStore.selectedToppings,
        qty: 1,
        masterGeneratedAt: props.masterGeneratedAt,
    });

    const result = await draftStore.submitLines([cartLine], debugEnabled.value);

    if (!result.ok) {
        isSubmitting.value = false;

        if (debugEnabled.value) {
            debugStore.pushTrace('order.submit.line.failed', {
                traceId,
                reason: result.reason ?? null,
                status: result.status ?? null,
                cart_item_id: cartLine.cart_item_id,
                display_full_name: cartLine.display_full_name,
            });
        }

        if (result.reason === 'empty' || result.reason === 'busy') {
            return;
        }

        if (result.reason === 'no_session') {
            window.alert(
                '卓またはセッションが無効です。テーブルを選び直してください。\n'
                + 'Table ou session invalide. Veuillez resélectionner une table.',
            );
            return;
        }

        if (result.status === 422 && typeof result.message === 'string' && result.message.trim() !== '') {
            window.alert(
                `${result.message.trim()}\n\n---\n`
                + '上記を確認のうえ、必要なら選択を修正して再送信してください。\n'
                + 'Vérifiez le message ci-dessus puis réessayez.',
            );
            return;
        }

        window.alert(
            '通信エラーが発生しました。もう一度お試しください。\n'
            + 'Erreur de communication. Veuillez réessayer.',
        );
        return;
    }

    menuStore.closeConfigModal();
    emit('added', cartLine);
}

function onClose() {
    isSubmitting.value = false;
    menuStore.closeConfigModal();
    emit('close');
}
</script>

<template>
    <Teleport to="body">
        <div
            v-if="menuStore.configModal.open && item"
            class="fixed inset-0 z-50 flex items-end justify-center sm:items-center"
            role="dialog"
            aria-modal="true"
        >
            <!-- オーバーレイ -->
            <div
                class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                @click="onClose"
            />

            <!-- モーダル本体 -->
            <div class="relative w-full max-w-lg rounded-t-3xl bg-slate-900 p-6 shadow-2xl sm:rounded-3xl">

                <!-- ヘッダー -->
                <div class="mb-5 flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold tracking-widest text-cyan-300">商品設定</p>
                        <h2 class="mt-1 text-xl font-bold text-white">{{ item.name }}</h2>
                        <p class="mt-0.5 text-sm text-slate-400">
                            ベース: {{ formatDT(item.from_price_minor ?? item.price_minor ?? item.base_price_minor ?? 0) }}
                        </p>
                    </div>
                    <button
                        type="button"
                        class="shrink-0 rounded-lg border border-slate-600 px-3 py-1.5 text-sm text-slate-200 hover:bg-slate-800"
                        @click="onClose"
                    >
                        ✕
                    </button>
                </div>

                <!-- Style 選択（ラジオ） -->
                <section v-if="opts.styles.length > 0" class="mb-5">
                    <p class="mb-2 text-sm font-semibold text-slate-200">
                        スタイル
                        <span
                            v-if="opts.rules.style_required"
                            class="ml-1 rounded bg-rose-500/20 px-1.5 py-0.5 text-[11px] font-bold text-rose-300"
                        >
                            必須
                        </span>
                    </p>
                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <li
                            v-for="style in opts.styles"
                            :key="style.id"
                        >
                            <button
                                type="button"
                                class="w-full rounded-xl border px-3 py-2.5 text-left text-sm transition"
                                :class="String(selectedStyleId) === String(style.id)
                                    ? 'border-cyan-400 bg-cyan-500/20 text-cyan-100'
                                    : 'border-slate-600 bg-slate-800/60 text-slate-200 hover:bg-slate-700/70'"
                                @click="selectStyle(style.id)"
                            >
                                <span
                                    :class="style.ui_hint === 'bold' ? 'font-bold' : 'font-medium'"
                                >
                                    {{ style.name }}
                                </span>
                                <span
                                    v-if="style.price_minor"
                                    class="mt-0.5 block text-xs text-slate-400"
                                >
                                    +{{ formatDT(style.price_minor) }}
                                </span>
                            </button>
                        </li>
                    </ul>

                    <!-- 必須未選択の警告 -->
                    <p
                        v-if="opts.rules.style_required && !selectedStyleId"
                        class="mt-2 text-xs text-rose-400"
                    >
                        スタイルを選択してください
                    </p>
                </section>

                <!-- Topping 選択（チェック） -->
                <section v-if="opts.toppings.length > 0" class="mb-5">
                    <p class="mb-2 text-sm font-semibold text-slate-200">トッピング</p>
                    <ul class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                        <li
                            v-for="topping in opts.toppings"
                            :key="topping.id"
                        >
                            <button
                                type="button"
                                class="w-full rounded-xl border px-3 py-2.5 text-left text-sm transition"
                                :class="isToppingSelected(topping.id)
                                    ? 'border-amber-400 bg-amber-500/20 text-amber-100'
                                    : 'border-slate-600 bg-slate-800/60 text-slate-200 hover:bg-slate-700/70'"
                                @click="toggleTopping(topping.id)"
                            >
                                <span class="font-medium">{{ topping.name }}</span>
                                <span
                                    v-if="topping.price_delta_minor ?? topping.price_minor"
                                    class="mt-0.5 block text-xs text-slate-400"
                                >
                                    +{{ formatDT(topping.price_delta_minor ?? topping.price_minor ?? 0) }}
                                </span>
                            </button>
                        </li>
                    </ul>
                </section>

                <!-- フッター: 合計と追加ボタン -->
                <div class="flex items-center justify-between gap-4 border-t border-slate-700 pt-4">
                    <div>
                        <p class="text-xs text-slate-400">単価合計</p>
                        <p class="text-2xl font-bold text-white">{{ previewPrice }}</p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex min-h-11 min-w-[10rem] items-center justify-center rounded-2xl px-6 py-3 text-sm font-bold transition"
                        :class="addButtonEnabled
                            ? 'bg-cyan-500 text-white hover:bg-cyan-400 active:scale-95'
                            : 'cursor-not-allowed bg-slate-700 text-slate-500 opacity-50'"
                        :disabled="!canAddToCart || isSubmitting || draftStore.isOrderSubmitting"
                        @click="onAddToCart"
                    >
                        <span
                            v-if="isSubmitting || draftStore.isOrderSubmitting"
                            class="inline-flex items-center gap-2"
                        >
                            <span
                                class="h-4 w-4 shrink-0 animate-spin rounded-full border-2 border-white border-t-transparent"
                                aria-hidden="true"
                            />
                            <span>Add to Table</span>
                        </span>
                        <span v-else>Add to Table</span>
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
