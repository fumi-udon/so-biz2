<script setup>
import { computed, ref } from 'vue';
import { formatDT } from '../utils/currency';

const props = defineProps({
    debug: {
        type: Object,
        required: true,
    },
    master: {
        type: Object,
        default: null,
    },
    cart: {
        type: Object,
        default: null,
    },
    menu: {
        type: Object,
        default: null,
    },
});

const expanded = ref(false);
const activeTab = ref('api');

const statusBadge = computed(() => {
    if (props.debug.apiStatus === 'ok') return { icon: '🟢', label: 'API OK' };
    if (props.debug.apiStatus === 'error') return { icon: '🔴', label: 'API Error' };
    return { icon: '⚪', label: 'API Idle' };
});

const localStorageKb = computed(() =>
    `${(props.debug.localStorageSize / 1024).toFixed(1)} KB`,
);
</script>

<template>
    <aside class="fixed inset-x-0 bottom-0 z-50 border-t border-slate-700 bg-slate-900/97 text-slate-100 backdrop-blur-sm">

        <!-- 最小化バー -->
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-2 text-left text-xs sm:text-sm"
            @click="expanded = !expanded"
        >
            <span class="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span>{{ statusBadge.icon }} {{ statusBadge.label }}</span>
                <span>RTT: {{ debug.lastBootstrapTime?.durationMs ?? '-' }}ms</span>
                <span>Master: {{ master?.menuItems?.length ?? 0 }}品 / {{ master?.tables?.length ?? 0 }}台</span>
                <span>Cart: {{ cart?.totalItemsCount ?? 0 }}点</span>
                <span v-if="cart?.isOrderSubmitting" class="text-amber-300">送信…</span>
            </span>
            <span class="shrink-0 text-slate-400">{{ expanded ? '▲ 閉じる' : '▼ DEBUG' }}</span>
        </button>

        <!-- 展開パネル -->
        <div v-if="expanded" class="border-t border-slate-700">

            <!-- タブ -->
            <div class="flex gap-1 border-b border-slate-700 px-4 pt-2">
                <button
                    v-for="tab in ['api', 'master', 'cart', 'menu', 'trace']"
                    :key="tab"
                    type="button"
                    :class="[
                        'rounded-t px-3 py-1 text-xs font-semibold uppercase tracking-wide',
                        activeTab === tab
                            ? 'bg-slate-700 text-white'
                            : 'text-slate-400 hover:text-slate-200',
                    ]"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                </button>
            </div>

            <!-- API タブ -->
            <div v-if="activeTab === 'api'" class="grid gap-2 px-4 py-3 text-xs sm:grid-cols-2 sm:text-sm">
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="text-slate-400">Bootstrap RTT</p>
                    <p class="text-lg font-bold">{{ debug.lastBootstrapTime?.durationMs ?? '-' }} ms</p>
                    <p class="text-slate-500">at: {{ debug.lastBootstrapTime?.at ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="text-slate-400">LocalStorage</p>
                    <p class="text-lg font-bold">{{ localStorageKb }}</p>
                    <p class="text-slate-500">generated_at: {{ debug.generatedAt ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2 sm:col-span-2">
                    <p class="mb-1 text-slate-400">API logs (最新5件)</p>
                    <ul class="space-y-1 text-slate-200">
                        <li
                            v-for="log in debug.apiLogs"
                            :key="`${log.at}-${log.name}`"
                            class="flex items-center justify-between"
                        >
                            <span :class="log.status >= 200 && log.status < 400 ? 'text-green-300' : 'text-rose-300'">
                                {{ log.name }} [{{ log.status }}]
                            </span>
                            <span>{{ log.durationMs }}ms</span>
                        </li>
                        <li v-if="debug.apiLogs.length === 0" class="text-slate-500">ログなし</li>
                    </ul>
                </div>
            </div>

            <!-- Master タブ -->
            <div v-if="activeTab === 'master'" class="grid gap-2 px-4 py-3 text-xs sm:grid-cols-3 sm:text-sm">
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="text-slate-400">categories</p>
                    <p class="text-2xl font-bold">{{ master?.categories?.length ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="text-slate-400">menuItems</p>
                    <p class="text-2xl font-bold">{{ master?.menuItems?.length ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="text-slate-400">tables</p>
                    <p class="text-2xl font-bold">{{ master?.tables?.length ?? '-' }}</p>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2 sm:col-span-3">
                    <p class="text-slate-400">items with options (style/topping)</p>
                    <p class="text-2xl font-bold text-cyan-300">{{ master?.itemsWithOptionsCount ?? '-' }}</p>
                    <p class="mt-1 text-slate-500">schema_version: {{ master?.schemaVersion ?? '-' }}</p>
                    <p class="text-slate-500">generated_at: {{ master?.generatedAt ?? '-' }}</p>
                </div>
            </div>

            <!-- Cart タブ -->
            <div v-if="activeTab === 'cart'" class="px-4 py-3 text-xs sm:text-sm">
                <div class="mb-2 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">draft key</p>
                        <p class="break-all font-mono text-slate-200">{{ cart?.draftKey ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">items count</p>
                        <p class="text-2xl font-bold">{{ cart?.totalItemsCount ?? 0 }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">total minor / DT</p>
                        <p class="text-2xl font-bold">{{ cart?.totalMinor ?? 0 }}</p>
                        <p class="text-slate-500">{{ formatDT(cart?.totalMinor ?? 0) }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2 sm:col-span-3">
                        <p class="text-slate-400">isOrderSubmitting</p>
                        <p class="text-xl font-bold" :class="cart?.isOrderSubmitting ? 'text-amber-300' : 'text-slate-500'">
                            {{ cart?.isOrderSubmitting ? 'true' : 'false' }}
                        </p>
                    </div>
                </div>
                <div
                    v-if="debug.lastOrderSubmit"
                    class="mb-2 rounded-lg border border-cyan-900/80 bg-slate-950/80 p-2 text-slate-200"
                >
                    <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-cyan-400">直近の注文送信（要約）</p>
                    <dl class="grid gap-1 text-[11px] sm:grid-cols-2 sm:text-xs">
                        <div><dt class="text-slate-500">at</dt><dd class="font-mono break-all">{{ debug.lastOrderSubmit.at }}</dd></div>
                        <div><dt class="text-slate-500">outcome</dt><dd class="font-bold">{{ debug.lastOrderSubmit.outcome }}</dd></div>
                        <div><dt class="text-slate-500">trace_id</dt><dd class="font-mono break-all">{{ debug.lastOrderSubmit.trace_id ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">client_submit_id</dt><dd class="font-mono break-all">{{ debug.lastOrderSubmit.client_submit_id ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">http_status</dt><dd>{{ debug.lastOrderSubmit.http_status ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">duration_ms</dt><dd>{{ debug.lastOrderSubmit.duration_ms ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">line_count</dt><dd>{{ debug.lastOrderSubmit.line_count ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">total_minor</dt><dd>{{ debug.lastOrderSubmit.total_minor ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">draft_key</dt><dd class="break-all font-mono">{{ debug.lastOrderSubmit.draft_key ?? '-' }}</dd></div>
                        <div><dt class="text-slate-500">order_id</dt><dd>{{ debug.lastOrderSubmit.order_id ?? '-' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-slate-500">error_message</dt><dd class="break-all text-rose-300">{{ debug.lastOrderSubmit.error_message ?? '-' }}</dd></div>
                    </dl>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="mb-1 text-slate-400">draft lines (新スキーマ v2)</p>
                    <ul class="space-y-2 text-slate-200">
                        <li
                            v-for="line in cart?.lines ?? []"
                            :key="line.cart_item_id"
                            class="rounded border border-slate-700 bg-slate-900/50 px-2 py-1.5"
                        >
                            <p class="font-medium text-white">{{ line.display_full_name }}</p>
                            <p class="text-slate-400">
                                {{ formatDT(line.total_unit_price_minor) }} × {{ line.qty }}
                                = {{ formatDT((line.total_unit_price_minor ?? 0) * (line.qty ?? 1)) }}
                            </p>
                            <p class="font-mono text-[10px] text-slate-600">id: {{ line.cart_item_id }}</p>
                        </li>
                        <li v-if="!cart?.lines?.length" class="text-slate-500">カート空</li>
                    </ul>
                </div>
            </div>

            <!-- Menu タブ -->
            <div v-if="activeTab === 'menu'" class="px-4 py-3 text-xs sm:text-sm">
                <div class="mb-2 grid gap-2 sm:grid-cols-3">
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">index built</p>
                        <p class="text-xl font-bold" :class="menu?.isIndexBuilt ? 'text-green-400' : 'text-rose-400'">
                            {{ menu?.isIndexBuilt ? 'YES' : 'NO' }}
                        </p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">active category</p>
                        <p class="font-mono text-slate-200">{{ menu?.activeCategoryId ?? '-' }}</p>
                    </div>
                    <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                        <p class="text-slate-400">visible items</p>
                        <p class="text-2xl font-bold">{{ menu?.visibleItems?.length ?? 0 }}</p>
                    </div>
                </div>
                <div class="rounded-lg border border-slate-700 bg-slate-950/60 p-2">
                    <p class="mb-1 text-slate-400">ConfigModal 状態</p>
                    <p>open: <span class="font-bold" :class="menu?.configModal?.open ? 'text-amber-300' : 'text-slate-500'">{{ menu?.configModal?.open }}</span></p>
                    <p>item: <span class="text-slate-200">{{ menu?.configModal?.masterItem?.name ?? 'none' }}</span></p>
                    <p>selectedStyleId: <span class="font-mono text-cyan-300">{{ menu?.configModal?.selectedStyleId ?? 'null' }}</span></p>
                    <p>canAddToCart: <span class="font-bold" :class="menu?.canAddToCart ? 'text-green-400' : 'text-rose-400'">{{ menu?.canAddToCart }}</span></p>
                </div>
            </div>

            <!-- Trace タブ -->
            <div v-if="activeTab === 'trace'" class="px-4 py-3 text-xs sm:text-sm">
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-slate-400">操作トレース (最新100件)</p>
                    <button
                        type="button"
                        class="rounded border border-slate-600 px-2 py-0.5 text-xs text-slate-200 hover:bg-slate-800"
                        @click="debug.clearTraceLogs()"
                    >
                        clear
                    </button>
                </div>
                <ul class="max-h-48 space-y-1 overflow-auto text-slate-200">
                    <li
                        v-for="trace in debug.traceLogs"
                        :key="`${trace.at}-${trace.event}`"
                        class="flex items-start justify-between gap-2"
                    >
                        <span class="truncate font-mono text-xs text-slate-300">{{ trace.event }}</span>
                        <span class="shrink-0 text-slate-500">{{ trace.at.slice(11, 23) }}</span>
                    </li>
                    <li v-if="debug.traceLogs.length === 0" class="text-slate-500">ログなし</li>
                </ul>
            </div>

        </div>
    </aside>
</template>
