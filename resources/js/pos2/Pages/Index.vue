<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useDebugStore } from '../stores/useDebugStore';
import { useMasterStore } from '../stores/useMasterStore';
import { useDraftStore } from '../stores/useDraftStore';
import { useTableStore } from '../stores/useTableStore';
import { useMenuStore } from '../stores/useMenuStore';
import { usePos2SessionUiStore } from '../stores/usePos2SessionUiStore';
import { useTableLabelStore } from '../stores/useTableLabelStore';
import { isTakeoutTableName } from '../utils/tableNameKind';
import DebugPanel from '../components/DebugPanel.vue';
import TableGrid from '../components/TableGrid.vue';
import TakeoutCustomerLabelModal from '../components/TakeoutCustomerLabelModal.vue';
import SessionRightColumn from '../components/SessionRightColumn.vue';
import CategoryRail from '../components/CategoryRail.vue';
import ProductGrid from '../components/ProductGrid.vue';
import ConfigModal from '../components/ConfigModal.vue';
import Pos2AppMenu from '../components/Pos2AppMenu.vue';
import ChangeTableModal from '../components/ChangeTableModal.vue';
import Pos2LineDeletePinModal from '../components/Pos2LineDeletePinModal.vue';
import { buildCartLineSnapshot } from '../utils/cartLineBuilder';
import { formatDT } from '../utils/currency';
import { flattenSessionOrderLines } from '../utils/sessionOrdersFlatLines';

const page = usePage();
const debugStore = useDebugStore();
const masterStore = useMasterStore();
const draftStore = useDraftStore();
const tableStore = useTableStore();
const menuStore = useMenuStore();
const sessionUi = usePos2SessionUiStore();
const labelStore = useTableLabelStore();

const shopId = computed(() => Number(page.props.shop_id ?? 0));

labelStore.setShopId(shopId.value);
labelStore.hydrateFromSessionStorage();

const debugEnabled = computed(() => page.props?.auth?.debug === true);
const posUi = computed(() => {
    const raw = page.props.pos_ui;
    return raw && typeof raw === 'object' ? raw : {};
});

/** Inertia `pos_ui` 由来の画面文言（欠損時は短い FR でフォールバック） */
const pos2Screen = computed(() => {
    const u = posUi.value;
    const pick = (key, frFallback) =>
        (typeof u[key] === 'string' && u[key].trim() !== '') ? u[key] : frFallback;
    return {
        pageTitle: pick('pos2_page_title', 'POS Caisse'),
        emptyTitle: pick('pos2_empty_state_title', '👈 Touchez une table à gauche'),
        emptyHint: pick('pos2_empty_state_hint', '📋 Ici : commandes, + ajouter, envoi cuisine.'),
        refreshTitle: pick('pos2_refresh_title', '🔄 Actualiser'),
        refreshAria: pick('pos2_refresh_aria', 'Actualiser les commandes'),
        closeMenu: pick('pos2_close_menu_btn', '✕ Fermer le menu'),
        addTitle: pick('pos2_add_title', '➕ Ajouter'),
        addAria: pick('pos2_add_aria', 'Ajouter un plat ou une boisson'),
        kdsLabel: pick('pos2_kds_label', '📤 Cuisine'),
        orderSubmitting: pick('pos2_order_submitting', '⏳ Envoi en cours…'),
        takeoutFabTitle: pick('pos2_takeout_fab_title', '👤 Nom du client'),
        takeoutFabLabel: pick('pos2_takeout_fab_label', 'Nom'),
        takeoutModal: {
            title: pick('pos2_takeout_modal_title', '👤 Nom du client (à emporter)'),
            hint: pick('pos2_takeout_modal_hint', '📱 Affichage sur cette tablette seulement.'),
            fieldName: pick('pos2_takeout_field_name_label', 'Nom'),
            fieldTel: pick('pos2_takeout_field_tel_label', '📞 Tél. (facultatif)'),
            placeholderName: pick('pos2_takeout_placeholder_name', 'ex : Dupont'),
            placeholderTel: pick('pos2_takeout_placeholder_tel', '06 12 34 56 78'),
            nameRequired: pick('pos2_takeout_name_required_error', '⚠️ Indiquez un nom.'),
            cancel: pick('pos2_takeout_btn_cancel', '✕ Annuler'),
            save: pick('pos2_takeout_btn_save', '✓ OK'),
        },
        recuConflict: pick('pos2_recu_conflict_alert', '🔄 Un autre appareil a modifié les données.\nVérifiez l’écran.'),
        recuFailed: pick('pos2_recu_failed_alert', '❌ Envoi cuisine impossible. Réessayez.'),
        tableMoveConflict: pick('pos2_table_move_conflict_alert', '🔄 Conflit : l’écran est synchronisé.'),
        tableMoveFailed: pick('pos2_table_move_failed_alert', '❌ Changement de table impossible.'),
        changeTableTitle: pick('change_table_title', '🪑 Changer de table'),
        changeTableCancel: pick('change_table_cancel', '✕ Fermer'),
        lineDeletePin: {
            title: pick('remove_line_auth_required_title', 'Validation responsable'),
            hint: pick('remove_line_auth_input_required', 'Sélectionnez un approbateur et saisissez le PIN.'),
            approverPlaceholder: pick('pos2_line_delete_approver_placeholder', '— Responsable —'),
            approverLabel: pick('pos2_line_delete_approver_label', 'Responsable'),
            pinLabel: pick('pos2_line_delete_pin_field_label', 'Code PIN'),
            cancel: pick('change_table_cancel', '✕ Fermer'),
            submit: pick('pos2_line_delete_pin_submit', 'Valider'),
            emptyApprovers: pick('pos2_line_delete_pin_no_approvers', 'Aucun responsable (niveau 3+) disponible.'),
            validationApprover: pick('pos2_line_delete_pin_err_approver', 'Choisissez un responsable.'),
            validationPin: pick('pos2_line_delete_pin_err_pin', 'Saisissez le PIN.'),
        },
    };
});

const isTableSelected = computed(() => tableStore.hasSelection);

const tilesByTableId = computed(() => {
    const m = {};
    for (const t of sessionUi.dashboardTiles ?? []) {
        const id = Number(t.restaurantTableId);
        if (Number.isFinite(id)) {
            m[id] = t;
        }
    }
    return m;
});

const RE_TABLE_CLIENT = /^T\d+/;
const RE_TABLE_STAFF = /^ST\d+/;
const RE_TABLE_TAKEOUT = /^TK\d+/;

const displayedTables = computed(() => {
    const rows = Array.isArray(masterStore.tables) ? masterStore.tables : [];
    const nameOf = (t) => (t?.name != null ? String(t.name) : '');

    const pick = (re, limit) => rows
        .filter((t) => re.test(nameOf(t)))
        .slice(0, limit);

    return [
        ...pick(RE_TABLE_CLIENT, masterStore.clientTableLimit),
        ...pick(RE_TABLE_STAFF, masterStore.staffTableLimit),
        ...pick(RE_TABLE_TAKEOUT, masterStore.takeoutTableLimit),
    ];
});

const selectedTableName = computed(() => {
    void labelStore.tableLabels;
    const tid = tableStore.selectedTableId;
    if (tid == null) return '';
    const row = (masterStore.tables ?? []).find((t) => Number(t.id) === Number(tid));
    const base = row?.name != null && String(row.name).trim() !== '' ? String(row.name) : `T${tid}`;
    if (!isTakeoutTableName(base)) {
        return base;
    }
    const sid = sessionUi.activeTableSessionId;
    if (sid == null || Number(sid) < 1) {
        return base;
    }
    const lab = labelStore.getLabel(sid);
    if (lab?.name) {
        return `${base} / ${lab.name}`;
    }
    return base;
});

/** 卓タイル1行目（客名付き TK 表示）— labelStore.tableLabels を追跡 */
const resolveTableDisplayNameFn = computed(() => {
    void labelStore.tableLabels;
    return (table) => {
        const base = table?.name != null && String(table.name).trim() !== ''
            ? String(table.name).trim()
            : `T${table?.id ?? ''}`;
        if (!isTakeoutTableName(base)) {
            return base;
        }
        const tile = tilesByTableId.value[Number(table.id)];
        const sid = tile?.activeTableSessionId != null && Number(tile.activeTableSessionId) > 0
            ? Number(tile.activeTableSessionId)
            : null;
        if (sid == null) {
            return base;
        }
        const lab = labelStore.getLabel(sid);
        if (lab?.name) {
            return `${base} / ${lab.name}`;
        }
        return base;
    };
});

const takeoutLabelModalOpen = ref(false);

const selectedTableIsTakeout = computed(() => {
    const tid = tableStore.selectedTableId;
    if (tid == null) return false;
    const row = (masterStore.tables ?? []).find((t) => Number(t.id) === Number(tid));
    return row ? isTakeoutTableName(row) : false;
});

const showTakeoutCustomerLabelFab = computed(() =>
    selectedTableIsTakeout.value
    && sessionUi.activeTableSessionId != null
    && Number(sessionUi.activeTableSessionId) > 0,
);

const takeoutLabelModalInitial = computed(() => {
    const sid = sessionUi.activeTableSessionId;
    if (sid == null || Number(sid) < 1) {
        return { name: '', tel: '' };
    }
    const lab = labelStore.getLabel(sid);
    return {
        name: lab?.name ?? '',
        tel: lab?.tel ?? '',
    };
});

/** 確定注文行（SessionRightColumn.flatLines と同一ソース） */
const sessionOrderFlatLines = computed(() =>
    flattenSessionOrderLines(sessionUi.sessionOrdersPayload),
);

/**
 * ADDITION / CLÔTURE 右端チップ: 右ペインに1行でも出ているときだけ。
 * 別セッションのペイロード残りは出さない（table_session_id でガード）。
 */
const showSettlementEdgeChips = computed(() => {
    if (!tableStore.hasSelection) {
        return false;
    }
    const sid = sessionUi.activeTableSessionId;
    if (sid == null || Number(sid) < 1) {
        return false;
    }
    const payload = sessionUi.sessionOrdersPayload;
    const pSid = payload?.table_session_id;
    if (pSid != null && Number(pSid) > 0 && Number(pSid) !== Number(sid)) {
        return false;
    }
    return sessionOrderFlatLines.value.length > 0;
});

/** テイクアウト客名 FAB（画面中央縦）と縦位置が被らないようずらす */
const settlementChipsYClass = computed(() =>
    showTakeoutCustomerLabelFab.value
        ? 'top-[38%] -translate-y-1/2'
        : 'top-1/2 -translate-y-1/2',
);

const pos2BridgeOrigin = typeof window !== 'undefined' ? window.location.origin : '';

function onPos2BridgeMessage(event) {
    if (typeof window === 'undefined' || event.origin !== pos2BridgeOrigin) {
        return;
    }
    const data = event.data;
    if (!data || data.type !== 'pos2-bridge' || typeof data.action !== 'string') {
        return;
    }
    const sidRaw = data.table_session_id;
    const intent = data.intent;

    if (data.action === 'pos-settlement-completed') {
        if (sidRaw != null && Number(sidRaw) > 0) {
            labelStore.clearLabel(Number(sidRaw));
            sessionUi.optimisticClearSession(Number(sidRaw));
        }
    } else if (data.action === 'receipt-preview-printed') {
        if (sidRaw != null && Number(sidRaw) > 0 && intent === 'addition') {
            sessionUi.optimisticUpdateTileStatusBySessionId(Number(sidRaw), 'billed');
        }
    }

    if (
        data.action === 'receipt-preview-printed'
        || data.action === 'close-receipt'
        || data.action === 'pos-settlement-completed'
    ) {
        void refreshAfterPos2Bridge();
    }
}

function openTakeoutLabelModal() {
    takeoutLabelModalOpen.value = true;
}

function closeTakeoutLabelModal() {
    takeoutLabelModalOpen.value = false;
}

function onTakeoutLabelSave(payload) {
    const sid = sessionUi.activeTableSessionId;
    if (sid == null || Number(sid) < 1) {
        closeTakeoutLabelModal();
        return;
    }
    labelStore.setLabel(sid, payload.name, payload.tel);
    closeTakeoutLabelModal();
}

/**
 * 行削除 PIN モーダル: 承認者＋PIN 送信。先にチャレンジを閉じてから楽観削除を再試行する。
 * @param {{ approverStaffId: number, approvalPin: string }} payload
 */
function onLineDeletePinSubmit(payload) {
    const ch = sessionUi.lineDeletePinChallenge;
    if (!ch) {
        return;
    }
    const lineId = ch.lineId;
    const sessionId = ch.sessionId;
    sessionUi.dismissLineDeletePinChallenge();
    void sessionUi.optimisticDeleteOrderLine(
        lineId,
        sessionId,
        payload.approverStaffId,
        payload.approvalPin,
    );
}

async function refreshAfterPos2Bridge() {
    await sessionUi.fetchTableDashboard({ silent: true });
    const sid = sessionUi.activeTableSessionId;
    if (sid != null && sid > 0) {
        await sessionUi.fetchSessionOrders(sid, { skipLoadingUi: true, silent: true });
    }
    if (debugEnabled.value) {
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
    }
}

/** ブリッジを開く直前の超楽観ガード（通信なし） */
function tryOpenSettlementBridge() {
    const u = posUi.value;
    const msgNoOrders = typeof u.bridge_settlement_guard_no_orders === 'string' && u.bridge_settlement_guard_no_orders.trim() !== ''
        ? u.bridge_settlement_guard_no_orders
        : "🧾 Total à 0 €\n\nAucune ligne sur l’addition. Ajoutez des articles avant d’ouvrir l’addition ou la clôture.";
    const msgUnacked = typeof u.bridge_settlement_guard_unacked === 'string' && u.bridge_settlement_guard_unacked.trim() !== ''
        ? u.bridge_settlement_guard_unacked
        : "⏳ Commandes non envoyées à la cuisine\n\nDes commandes « placées » ne sont pas encore validées. Touchez d’abord « Reçu staff », puis réessayez.";
    const msgNoSession = typeof u.bridge_settlement_guard_no_session === 'string' && u.bridge_settlement_guard_no_session.trim() !== ''
        ? u.bridge_settlement_guard_no_session
        : "⚠️ Session de table introuvable\n\nResélectionnez une table, puis réessayez.";

    if (Number(sessionUi.sessionTotalMinor) === 0) {
        window.alert(msgNoOrders);
        return false;
    }
    if (sessionUi.hasUnackedPlacedOrders) {
        window.alert(msgUnacked);
        return false;
    }
    const sid = sessionUi.activeTableSessionId;
    if (sid == null || Number(sid) < 1) {
        window.alert(msgNoSession);
        return false;
    }
    return true;
}

function openAdditionBridge() {
    if (!tryOpenSettlementBridge()) {
        return;
    }
    const sid = sessionUi.activeTableSessionId;
    const url = `${window.location.origin}/pos2/bridge/sessions/${Number(sid)}/addition`;
    window.open(url, '_blank');
    sessionUi.optimisticUpdateTileStatusBySessionId(Number(sid), 'billed');
}

function openClotureBridge() {
    if (!tryOpenSettlementBridge()) {
        return;
    }
    const sid = sessionUi.activeTableSessionId;
    const url = `${window.location.origin}/pos2/bridge/sessions/${Number(sid)}/cloture`;
    window.open(url, '_blank');
}

function hasDraftBadgeForTable(tableId) {
    const tile = tilesByTableId.value[Number(tableId)];
    const sid = tile?.activeTableSessionId;
    if (sid != null && Number(sid) > 0) {
        return draftStore.hasDraftForTable(shopId.value, sid);
    }
    return draftStore.hasDraftForTable(shopId.value, tableId);
}

// ---------------------------------------------------------------------------
// トレースヘルパー
// ---------------------------------------------------------------------------

function safeTrace(event, payload) {
    if (!debugEnabled.value) return;
    try {
        debugStore.pushTrace(event, payload);
    } catch {
        // 調査コードは本ロジックを止めない
    }
}

// ---------------------------------------------------------------------------
// テーブル操作
// ---------------------------------------------------------------------------

/**
 * 0ms でタイルキャッシュ（Pinia）を即反映し、API 更新はバックグラウンドで行う。
 * Stale-While-Revalidate: キャッシュ値で即画面遷移 → 裏で差分更新。
 *
 * **再タップ:** 既に選択中の卓タイルを再度タップした場合は、卓切り替えフローを走らせず
 * `adding` → `monitoring` のみ戻し、**`[↻]` と同様**に `fetchTableDashboard({ silent: true })` → 当該タイルのセッションで
 * `fetchSessionOrders`（`skipLoadingUi`）を裏実行し、左タイルと右リストの認知ズレを防ぐ。
 */
function onSelectTable(tableId) {
    const numericId = Number(tableId);
    if (!Number.isFinite(numericId)) {
        return;
    }

    if (tableStore.selectedTableId === numericId) {
        if (sessionUi.uiMode === 'adding') {
            sessionUi.exitToMonitoring();
        }
        void (async () => {
            await sessionUi.fetchTableDashboard({ silent: true });
            await applyDashboardDeltaToSelectedTable(numericId, { skipLoadingUi: true });
            if (debugEnabled.value) {
                safeTrace('table.select.retap', {
                    tableId: numericId,
                    sessionId: sessionUi.activeTableSessionId,
                });
                debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
            }
        })();
        return;
    }

    const traceId = debugEnabled.value ? debugStore.nextTraceId('table-select') : null;

    // ① 0ms: 選択状態だけ即 Pinia に反映（DOM が切り替わる）
    tableStore.selectTable(tableId, {
        traceId,
        debugEnabled: debugEnabled.value,
        cartCount: draftStore.totalItemsCount,
    });

    // ② 0ms: キャッシュ済みタイルからセッション ID を読んで即 sync
    const cachedTile = tilesByTableId.value[Number(tableId)] ?? null;
    const cachedSessionId = cachedTile?.activeTableSessionId != null
        && Number(cachedTile.activeTableSessionId) > 0
        ? Number(cachedTile.activeTableSessionId)
        : null;

    sessionUi.syncSelectionFromTile(Number(tableId), cachedSessionId);

    // ③ 0ms: ドラフトを LocalStorage から即復元（通信なし）
    draftStore.shopId = shopId.value;
    if (cachedSessionId != null) {
        draftStore.tableSessionId = String(cachedSessionId);
        draftStore.pendingTableId = null;
        draftStore.loadDraftFromStorage(shopId.value, cachedSessionId, {
            traceId,
            debugEnabled: debugEnabled.value,
        });
    } else {
        // 空卓: session なし。pendingTableId をセットしてカート積みを許可する（Bug1 fix）
        draftStore.lines = [];
        draftStore.tableSessionId = null;
        draftStore.pendingTableId = Number(tableId);
        draftStore.loadedAt = new Date().toISOString();
    }

    if (debugEnabled.value) {
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
        debugStore.refreshLocalStorageSize();
    }

    // ④ バックグラウンド: API を叩いて差分があれば Pinia が自動更新（await しない）
    void _revalidateTableDataInBackground(Number(tableId), traceId);
}

/**
 * バックグラウンド API 再検証。失敗しても UI は既に表示済みなのでフリーズしない。
 * @param {number} tableId
 * @param {string|null} traceId
 */
async function _revalidateTableDataInBackground(tableId, traceId = null) {
    await sessionUi.fetchTableDashboard();

    // ダッシュボード更新後、セッション ID が変わっていれば追従する
    const freshTile = tilesByTableId.value[tableId] ?? null;
    const freshSessionId = freshTile?.activeTableSessionId != null
        && Number(freshTile.activeTableSessionId) > 0
        ? Number(freshTile.activeTableSessionId)
        : null;

    if (freshSessionId != null) {
        // キャッシュと異なるセッション ID がサーバーから返った場合のみドラフトを更新
        const prevSid = sessionUi.activeTableSessionId;
        if (prevSid !== freshSessionId) {
            sessionUi.activeTableSessionId = freshSessionId;
            draftStore.tableSessionId = String(freshSessionId);
            draftStore.pendingTableId = null;
            draftStore.loadDraftFromStorage(shopId.value, freshSessionId, {
                traceId,
                debugEnabled: debugEnabled.value,
            });
        }
        await sessionUi.fetchSessionOrders(freshSessionId);
    } else if (sessionUi.activeTableSessionId != null) {
        // セッションが消えた（会計済みなど）→ pendingTableId に切り替えて空卓扱いに戻す
        sessionUi.activeTableSessionId = null;
        draftStore.tableSessionId = null;
        draftStore.pendingTableId = tableId;
        draftStore.lines = [];
    }

    if (debugEnabled.value) {
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
        debugStore.refreshLocalStorageSize();
    }
}

function backToTableGrid() {
    const traceId = debugEnabled.value ? debugStore.nextTraceId('table-clear') : null;
    tableStore.clearSelection({ traceId, debugEnabled: debugEnabled.value });
    draftStore.lines = [];
    draftStore.tableSessionId = null;
    menuStore.closeConfigModal();
    sessionUi.$reset();
    void sessionUi.fetchTableDashboard();

    if (debugEnabled.value) {
        debugStore.setUiSnapshot(null, 0);
    }
}

/**
 * Bug1 fix: 卓が選ばれていれば Add は常に可能。
 * セッションなし（空卓）でも商品タップで即送信。送信時にサーバーが getOrCreate する。
 */
const canAddOrSubmit = computed(() => tableStore.hasSelection);

function onTapAdd() {
    sessionUi.enterAddingMode();
}

// ---------------------------------------------------------------------------
// カート操作
// ---------------------------------------------------------------------------

/** スタイル必須・オプションありは必ず ConfigModal へ（即時追加で style 欠落バグを防ぐ） */
function itemNeedsConfigModal(masterItem) {
    const p = masterItem?.options_payload;
    if (!p || typeof p !== 'object') return false;
    if (p.rules?.style_required === true) {
        return true;
    }
    return (Array.isArray(p.styles) && p.styles.length > 0)
        || (Array.isArray(p.toppings) && p.toppings.length > 0);
}

function onAddSimple(masterItem) {
    if (!canAddOrSubmit.value) return;
    if (draftStore.isOrderSubmitting) return;
    if (itemNeedsConfigModal(masterItem)) {
        menuStore.openConfigModal(masterItem);
        return;
    }

    const cartLine = buildCartLineSnapshot({
        masterItem,
        selectedOption: null,
        selectedToppings: [],
        qty: 1,
        masterGeneratedAt: masterStore.generatedAt ?? '',
    });

    draftStore.submitLines([cartLine], debugEnabled.value).then((result) => {
        if (!result.ok) {
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
                    + '上記を確認のうえ、必要なら再度お試しください。\n'
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

        sessionUi.exitToMonitoring();

        if (debugEnabled.value) {
            debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
            debugStore.refreshLocalStorageSize();
        }
    }).catch(() => {
        window.alert(
            '通信エラーが発生しました。もう一度お試しください。\n'
            + 'Erreur de communication. Veuillez réessayer.',
        );
    });
}

function onCartAdded() {
    // ConfigModal から Add to Table 成功後 → 即 monitoring に戻る
    sessionUi.exitToMonitoring();

    if (debugEnabled.value) {
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
        debugStore.refreshLocalStorageSize();
    }
}

async function onRefreshDetail() {
    const sid = sessionUi.activeTableSessionId;
    await sessionUi.fetchTableDashboard();
    if (sid != null && sid > 0) {
        await sessionUi.fetchSessionOrders(sid);
    }
}

const sendKdsBusy = ref(false);
async function onSendKds() {
    const sid = sessionUi.activeTableSessionId;
    if (sid == null) return;
    sendKdsBusy.value = true;
    let res;
    try {
        res = await sessionUi.sendRecuStaff();
    } finally {
        sendKdsBusy.value = false;
    }
    if (!res.ok && res.status === 409) {
        window.alert(pos2Screen.value.recuConflict);
    } else if (!res.ok) {
        window.alert(pos2Screen.value.recuFailed);
    }
}

// ---------------------------------------------------------------------------
// 卓移動（Change table）
// ---------------------------------------------------------------------------

const changeTableModalOpen = ref(false);
const changeTableBusy = ref(false);
const snackbar = ref({ show: false, message: '' });
let snackbarHideTimer = null;

function showPos2Snackbar(message) {
    snackbar.value = { show: true, message: String(message || '') };
    if (snackbarHideTimer != null) {
        clearTimeout(snackbarHideTimer);
    }
    snackbarHideTimer = setTimeout(() => {
        snackbar.value = { show: false, message: '' };
        snackbarHideTimer = null;
    }, 3200);
}

const canPos2ChangeTable = computed(() =>
    tableStore.hasSelection
    && sessionUi.activeTableSessionId != null
    && Number(sessionUi.activeTableSessionId) > 0,
);

const changeTableCandidates = computed(() => {
    const currentId = tableStore.selectedTableId;
    const tiles = sessionUi.dashboardTiles ?? [];
    const out = [];
    for (const t of tiles) {
        const id = Number(t.restaurantTableId);
        if (!Number.isFinite(id) || id < 1) {
            continue;
        }
        if (currentId != null && id === Number(currentId)) {
            continue;
        }
        if (String(t.category ?? '') !== 'customer') {
            continue;
        }
        const asid = t.activeTableSessionId;
        if (asid != null && Number(asid) > 0) {
            continue;
        }
        const nameRaw = t.restaurantTableName;
        const name = (nameRaw != null && String(nameRaw).trim() !== '')
            ? String(nameRaw).trim()
            : `T${id}`;
        out.push({ id, name });
    }
    out.sort((a, b) => a.name.localeCompare(b.name, 'ja'));
    return out;
});

function openChangeTableModalFromMenu() {
    changeTableModalOpen.value = true;
}

/**
 * 卓移動成功後: ダッシュボード再取得 → 選択卓と Pinia セッション UI を先卓に同期 → 注文再取得。
 * @param {number} destTableId
 */
async function applyAfterTableMove(destTableId) {
    draftStore.shopId = shopId.value;
    await sessionUi.fetchTableDashboard();
    const traceId = debugEnabled.value ? debugStore.nextTraceId('table-move') : null;
    tableStore.selectTable(destTableId, {
        traceId,
        debugEnabled: debugEnabled.value,
        cartCount: draftStore.totalItemsCount,
    });
    const sid = sessionUi.activeTableSessionId;
    sessionUi.syncSelectionFromTile(destTableId, sid);
    if (sid != null && sid > 0) {
        draftStore.tableSessionId = String(sid);
        draftStore.pendingTableId = null;
        draftStore.loadDraftFromStorage(shopId.value, sid, {
            traceId,
            debugEnabled: debugEnabled.value,
        });
        await sessionUi.fetchSessionOrders(sid);
    }
    if (debugEnabled.value) {
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
    }
}

async function onConfirmChangeTable(destTableId) {
    if (changeTableBusy.value) {
        return;
    }
    changeTableBusy.value = true;
    try {
        const res = await sessionUi.submitChangeTable(Number(destTableId));
        if (!res.ok) {
            if (res.status === 409) {
                const msg = typeof res.body?.message === 'string' && res.body.message.trim() !== ''
                    ? res.body.message.trim()
                    : pos2Screen.value.tableMoveConflict;
                window.alert(msg);
                changeTableModalOpen.value = false;
                return;
            }
            const errMsg = typeof res.body?.message === 'string' && res.body.message.trim() !== ''
                ? res.body.message.trim()
                : pos2Screen.value.tableMoveFailed;
            window.alert(errMsg);
            return;
        }
        changeTableModalOpen.value = false;
        await applyAfterTableMove(Number(destTableId));
        showPos2Snackbar(
            typeof posUi.value.change_table_success === 'string' && posUi.value.change_table_success.trim() !== ''
                ? posUi.value.change_table_success
                : '卓を移動しました。',
        );
    } finally {
        changeTableBusy.value = false;
    }
}

// ---------------------------------------------------------------------------
// ダッシュボード自動監視（QR 注文の F5 解消）— 15 秒ポーリング・非ブロッキング
// ---------------------------------------------------------------------------

const DASHBOARD_POLL_MS = 15000;
let dashboardPollTimerId = null;
let dashboardPollInFlight = false;

/**
 * タイルから「この卓で注文が増えたか」等を判定する軽量スナップショット。
 * @param {number} tableId
 */
function snapshotTileForTable(tableId) {
    const t = sessionUi.dashboardTiles?.find((x) => Number(x.restaurantTableId) === Number(tableId));
    if (!t) return null;
    const sid = t.activeTableSessionId != null && Number(t.activeTableSessionId) > 0
        ? Number(t.activeTableSessionId)
        : 0;
    return {
        activeTableSessionId: sid,
        relevantPosOrderCount: Number(t.relevantPosOrderCount ?? 0),
        sessionTotalMinor: Number(t.sessionTotalMinor ?? 0),
        unackedPlacedPosOrderCount: Number(t.unackedPlacedPosOrderCount ?? 0),
        unackedPlacedLineExists: Boolean(t.unackedPlacedLineExists),
        uiStatus: String(t.uiStatus ?? ''),
    };
}

function tileSyncSnapshotsEqual(a, b) {
    if (a === b) return true;
    if (a == null || b == null) return a === b;
    return a.activeTableSessionId === b.activeTableSessionId
        && a.relevantPosOrderCount === b.relevantPosOrderCount
        && a.sessionTotalMinor === b.sessionTotalMinor
        && a.unackedPlacedPosOrderCount === b.unackedPlacedPosOrderCount
        && a.unackedPlacedLineExists === b.unackedPlacedLineExists
        && a.uiStatus === b.uiStatus;
}

/**
 * ダッシュボード取得済みの `dashboardTiles` を前提に、当該卓のセッション ID と右ペイン注文を同期。
 * ポーリング差分時・同卓再タップ（`fetchTableDashboard` 直後）の双方で利用。
 *
 * @param {number} tableId
 * @param {{ skipLoadingUi?: boolean }} [options]
 */
async function applyDashboardDeltaToSelectedTable(tableId, options = {}) {
    const skipLoadingUi = options.skipLoadingUi === true;
    const freshTile = sessionUi.dashboardTiles?.find((t) => Number(t.restaurantTableId) === Number(tableId)) ?? null;
    const freshSessionId = freshTile?.activeTableSessionId != null
        && Number(freshTile.activeTableSessionId) > 0
        ? Number(freshTile.activeTableSessionId)
        : null;

    if (freshSessionId != null) {
        const prevSid = sessionUi.activeTableSessionId;
        if (prevSid !== freshSessionId) {
            sessionUi.activeTableSessionId = freshSessionId;
            draftStore.tableSessionId = String(freshSessionId);
            draftStore.pendingTableId = null;
            draftStore.loadDraftFromStorage(shopId.value, freshSessionId, {
                traceId: null,
                debugEnabled: false,
            });
        }
        await sessionUi.fetchSessionOrders(freshSessionId, {
            silent: true,
            ...(skipLoadingUi ? { skipLoadingUi: true } : {}),
        });
    } else if (sessionUi.activeTableSessionId != null) {
        sessionUi.activeTableSessionId = null;
        draftStore.tableSessionId = null;
        draftStore.pendingTableId = tableId;
        draftStore.lines = [];
    }
}

/**
 * 定期ポーリング 1 ティック。await しないで呼ぶ（void）。
 * 成功時はトレース/API ログを積まない（デバッグパネル汚染防止）。失敗時のみ軽く trace。
 */
async function runDashboardPollTick() {
    if (dashboardPollInFlight) return;
    if (draftStore.isOrderSubmitting || sendKdsBusy.value) return;

    const selectedId = tableStore.selectedTableId;
    const beforeSnap = selectedId != null ? snapshotTileForTable(Number(selectedId)) : null;

    dashboardPollInFlight = true;
    try {
        const res = await sessionUi.fetchTableDashboard({ silent: true });
        if (!res.ok) {
            if (debugEnabled.value) {
                safeTrace('pos2.poll.dashboard.failed', {
                    status: res.status ?? 0,
                    reason: res.reason ?? null,
                });
            }
            return;
        }

        if (selectedId == null) return;

        const afterSnap = snapshotTileForTable(Number(selectedId));
        if (tileSyncSnapshotsEqual(beforeSnap, afterSnap)) return;

        await applyDashboardDeltaToSelectedTable(Number(selectedId));
    } finally {
        dashboardPollInFlight = false;
    }
}

// ---------------------------------------------------------------------------
// bootstrap
// ---------------------------------------------------------------------------

async function loadBootstrap() {
    const started = performance.now();
    const traceId = debugEnabled.value ? debugStore.nextTraceId('bootstrap') : null;

    safeTrace('bootstrap.request.started', { traceId, url: '/pos2/api/bootstrap' });

    try {
        const response = await fetch('/pos2/api/bootstrap', {
            headers: { Accept: 'application/json' },
        });
        const duration = performance.now() - started;

        if (!response.ok) {
            if (debugEnabled.value) {
                debugStore.pushApiLog('bootstrap', response.status, duration);
                safeTrace('bootstrap.request.failed', {
                    traceId,
                    status: response.status,
                    durationMs: Math.round(duration),
                });
            }
            return;
        }

        const payload = await response.json();
        masterStore.applyPayload(payload, shopId.value);

        menuStore.buildIndex(masterStore.categories, masterStore.menuItems);

        if (debugEnabled.value) {
            const invalidOptionsCount = masterStore.menuItems.filter((item) => {
                const p = item?.options_payload;
                return !p || !Array.isArray(p.styles) || !Array.isArray(p.toppings);
            }).length;

            debugStore.recordBootstrap(duration, payload.generated_at ?? null);
            debugStore.pushApiLog('bootstrap', response.status, duration);
            debugStore.refreshLocalStorageSize();
            debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
            safeTrace('bootstrap.request.succeeded', {
                traceId,
                status: response.status,
                durationMs: Math.round(duration),
                schemaVersion: payload.schema_version ?? null,
                menuItems: masterStore.menuItems.length,
                categories: masterStore.categories.length,
                tables: masterStore.tables.length,
                itemsWithOptions: masterStore.itemsWithOptionsCount,
                invalidOptionsCount,
                menuIndexBuilt: menuStore.isIndexBuilt,
            });
        }
    } catch (error) {
        const duration = performance.now() - started;
        if (debugEnabled.value) {
            debugStore.pushApiLog('bootstrap', 0, duration);
            safeTrace('bootstrap.request.exception', {
                traceId,
                durationMs: Math.round(duration),
                message: error instanceof Error ? error.message : 'unknown error',
            });
        }
    }
}

async function onPos2StoragePurged() {
    await loadBootstrap();
    if (masterStore.categories.length > 0) {
        menuStore.buildIndex(masterStore.categories, masterStore.menuItems);
    }
    await sessionUi.fetchTableDashboard();
    if (debugEnabled.value) {
        debugStore.refreshLocalStorageSize();
        safeTrace('pos2.dev.storage_purged', { shopId: shopId.value });
    }
}

onMounted(async () => {
    window.addEventListener('message', onPos2BridgeMessage);

    const restoredFromStorage = masterStore.loadFromStorage(shopId.value);

    if (restoredFromStorage) {
        menuStore.buildIndex(masterStore.categories, masterStore.menuItems);
    }

    safeTrace('screen.mounted', {
        shopId: shopId.value,
        restoredFromStorage,
        generatedAt: masterStore.generatedAt,
        menuIndexBuilt: menuStore.isIndexBuilt,
    });

    if (debugEnabled.value) {
        debugStore.refreshLocalStorageSize();
        debugStore.setUiSnapshot(tableStore.selectedTableId, draftStore.totalItemsCount);
    }

    await loadBootstrap();
    await sessionUi.fetchTableDashboard();

    dashboardPollTimerId = setInterval(() => {
        void runDashboardPollTick();
    }, DASHBOARD_POLL_MS);
});

onUnmounted(() => {
    window.removeEventListener('message', onPos2BridgeMessage);
    if (dashboardPollTimerId != null) {
        clearInterval(dashboardPollTimerId);
        dashboardPollTimerId = null;
    }
    if (snackbarHideTimer != null) {
        clearTimeout(snackbarHideTimer);
        snackbarHideTimer = null;
    }
});
</script>

<template>
    <Head :title="pos2Screen.pageTitle" />

    <main
        class="mx-auto flex min-h-[100dvh] w-full max-w-[1680px] flex-col bg-slate-950 px-3 py-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] text-slate-100 md:px-4 md:py-4"
    >
        <!-- タブレット優先: 左 3 列卓グリッド常駐 + 右で注文・操作（§9） -->
        <div class="flex min-h-0 flex-1 flex-col gap-3 md:min-h-[calc(100dvh-1.5rem)] md:flex-row md:gap-4">
            <aside
                class="flex max-h-[min(52vh,28rem)] w-full shrink-0 flex-col overflow-y-auto overflow-x-hidden min-h-0 md:max-h-none md:w-[min(100%,380px)] md:max-w-[42vw] lg:max-w-md"
            >
                <TableGrid
                    layout-variant="split"
                    :tables="displayedTables"
                    :selected-table-id="tableStore.selectedTableId"
                    :debug-enabled="debugEnabled"
                    :has-draft-for-table="hasDraftBadgeForTable"
                    :tiles-by-table-id="tilesByTableId"
                    :resolve-table-display-name="resolveTableDisplayNameFn"
                    @select="onSelectTable"
                />
            </aside>

            <section class="flex min-h-0 min-w-0 flex-1 flex-col">
                <div
                    v-if="!isTableSelected"
                    class="flex min-h-[12rem] flex-1 flex-col items-center justify-center rounded-2xl border border-dashed border-slate-600 bg-slate-900/50 px-4 py-10 text-center md:min-h-0"
                >
                    <p class="text-sm font-semibold text-slate-300 dark:text-slate-200">
                        {{ pos2Screen.emptyTitle }}
                    </p>
                    <p class="mt-2 max-w-sm text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ pos2Screen.emptyHint }}
                    </p>
                </div>

                <template v-else>
                    <!-- ツールバー: 1行目=更新・閉じる・KITCHEN / 2行目=卓名ラベル・追加 -->
                    <div
                        class="mb-3 flex flex-col gap-2 border-b border-slate-700/90 pb-3 md:gap-2.5"
                    >
                        <div class="flex flex-wrap items-center gap-2 md:gap-3">
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 border-emerald-600 bg-emerald-600 text-white shadow-md transition hover:bg-emerald-500 focus:outline-none disabled:cursor-not-allowed disabled:opacity-40 dark:border-emerald-500 dark:hover:bg-emerald-500"
                                :disabled="draftStore.isOrderSubmitting"
                                :title="pos2Screen.refreshTitle"
                                :aria-label="pos2Screen.refreshAria"
                                @click="onRefreshDetail"
                            >
                                <span class="text-lg leading-none" aria-hidden="true">↻</span>
                            </button>

                            <div
                                class="hidden h-8 w-px shrink-0 bg-slate-600 md:block dark:bg-slate-500"
                                aria-hidden="true"
                            />

                            <button
                                v-if="sessionUi.uiMode === 'adding'"
                                type="button"
                                class="min-h-11 rounded-xl border border-slate-500 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800 dark:border-slate-500 dark:text-slate-200 dark:hover:bg-slate-800"
                                @click="sessionUi.exitToMonitoring()"
                            >
                                {{ pos2Screen.closeMenu }}
                            </button>
                            <button
                                type="button"
                                class="min-h-11 min-w-[8.5rem] shrink-0 rounded-xl border-2 border-indigo-800 bg-indigo-900 px-4 py-2 text-sm font-black uppercase tracking-wide text-white shadow-md transition hover:bg-indigo-800 disabled:cursor-not-allowed disabled:opacity-45 dark:border-indigo-600 dark:bg-indigo-950 dark:text-white dark:hover:bg-indigo-900"
                                :disabled="sendKdsBusy || !sessionUi.hasUnackedPlacedOrders || draftStore.isOrderSubmitting"
                                @click="onSendKds"
                            >
                                {{ pos2Screen.kdsLabel }}
                            </button>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 md:gap-3">
                            <div
                                :key="String(tableStore.selectedTableId ?? '')"
                                class="pos2-table-label-fx relative isolate inline-flex max-w-[min(100%,20rem)] shrink-0 overflow-hidden rounded-lg shadow-sm"
                            >
                                <span
                                    class="relative z-[1] min-w-0 max-w-full truncate rounded-lg bg-amber-300 px-3 py-2 text-sm font-black tracking-tight text-amber-950 dark:bg-amber-300 dark:text-amber-950"
                                >
                                    {{ selectedTableName }}
                                </span>
                                <span
                                    class="pointer-events-none absolute inset-0 z-[2] overflow-hidden rounded-lg"
                                    aria-hidden="true"
                                >
                                    <span class="pos2-table-label-fx__beam" />
                                </span>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border-2 border-sky-500 bg-sky-400 text-sky-950 shadow-md transition hover:bg-sky-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-900 disabled:cursor-not-allowed disabled:opacity-40 dark:border-sky-400 dark:bg-sky-500 dark:text-sky-950 dark:hover:bg-sky-400 dark:focus-visible:ring-offset-slate-950"
                                :disabled="!canAddOrSubmit || draftStore.isOrderSubmitting"
                                :title="pos2Screen.addTitle"
                                :aria-label="pos2Screen.addAria"
                                @click="onTapAdd"
                            >
                                <svg
                                    class="h-6 w-6 shrink-0"
                                    xmlns="http://www.w3.org/2000/svg"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke-width="2.25"
                                    stroke="currentColor"
                                    aria-hidden="true"
                                >
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        d="M12 4.5v15m7.5-7.5h-15"
                                    />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div class="relative flex min-h-0 flex-1 flex-col">
                        <div
                            class="pointer-events-none select-none absolute inset-0 z-[1] flex items-center justify-center overflow-hidden"
                            aria-hidden="true"
                        >
                            <span
                                class="max-w-[min(100%,42rem)] truncate px-3 text-center text-4xl font-black uppercase tracking-tight text-slate-200/[0.09] dark:text-white/[0.1] sm:text-5xl"
                            >
                                {{ selectedTableName }}
                            </span>
                        </div>
                        <div
                            class="relative z-[2] flex min-h-0 flex-1 flex-col gap-3"
                            :class="sessionUi.uiMode === 'adding' ? '' : 'lg:flex-row lg:gap-4'"
                        >
                            <div
                                v-if="sessionUi.uiMode === 'monitoring'"
                                class="flex min-h-[240px] flex-1 flex-col lg:min-h-[320px]"
                            >
                                <SessionRightColumn
                                    class="min-h-0 flex-1"
                                    :session-orders-payload="sessionUi.sessionOrdersPayload"
                                    :loading-confirmed="sessionUi.sessionOrdersLoading"
                                    :has-unacked-placed="sessionUi.hasUnackedPlacedOrders"
                                    :send-kds-busy="sendKdsBusy"
                                    :hide-kds-banner="true"
                                    @send-kds="onSendKds"
                                />
                            </div>

                            <template v-else>
                                <div
                                    class="flex min-h-0 min-h-[220px] flex-1 flex-col overflow-hidden rounded-2xl border border-slate-700/80 bg-slate-900/70 p-3 lg:min-h-[320px]"
                                >
                                    <CategoryRail
                                        :categories="masterStore.categories"
                                        :active-category-id="menuStore.activeCategoryId"
                                        class="mb-3 shrink-0"
                                        @select="menuStore.selectCategory"
                                    />
                                    <div class="min-h-0 flex-1 overflow-y-auto">
                                        <ProductGrid
                                            :items="menuStore.visibleItems"
                                            @open-modal="menuStore.openConfigModal"
                                            @add-simple="onAddSimple"
                                        />
                                    </div>
                                </div>
                                <div
                                    class="flex min-h-[min(40vh,22rem)] w-full shrink-0 flex-col lg:w-[min(100%,360px)] lg:max-w-md"
                                >
                                    <SessionRightColumn
                                        class="min-h-0 flex-1"
                                        :session-orders-payload="sessionUi.sessionOrdersPayload"
                                        :loading-confirmed="sessionUi.sessionOrdersLoading"
                                        :has-unacked-placed="sessionUi.hasUnackedPlacedOrders"
                                        :send-kds-busy="sendKdsBusy"
                                        :hide-kds-banner="true"
                                        @send-kds="onSendKds"
                                    />
                                </div>
                            </template>
                        </div>

                        <div
                            v-if="draftStore.isOrderSubmitting"
                            class="absolute inset-0 z-40 flex flex-col items-center justify-center rounded-2xl bg-slate-950/85 text-center backdrop-blur-sm"
                            role="status"
                            aria-live="polite"
                            aria-busy="true"
                        >
                            <div
                                class="h-11 w-11 animate-spin rounded-full border-4 border-cyan-400 border-t-transparent"
                                aria-hidden="true"
                            />
                            <p class="mt-4 px-4 text-sm font-semibold text-cyan-100 dark:text-cyan-100">
                                {{ pos2Screen.orderSubmitting }}
                            </p>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </main>

    <div
        v-if="shopId > 0 && isTableSelected"
        class="fixed right-[max(0.75rem,env(safe-area-inset-right))] top-[max(0.75rem,env(safe-area-inset-top))] z-[55]"
    >
        <Pos2AppMenu
            :shop-id="shopId"
            :can-change-table="canPos2ChangeTable"
            @purged="onPos2StoragePurged"
            @open-change-table="openChangeTableModalFromMenu"
        />
    </div>

    <ChangeTableModal
        :open="changeTableModalOpen"
        :candidates="changeTableCandidates"
        :busy="changeTableBusy"
        :title="pos2Screen.changeTableTitle"
        :hint="posUi.change_table_hint || ''"
        :empty-text="posUi.change_table_empty || ''"
        :cancel-label="pos2Screen.changeTableCancel"
        @close="changeTableModalOpen = false"
        @confirm="onConfirmChangeTable"
    />

    <div
        v-if="snackbar.show"
        class="pointer-events-none fixed bottom-6 left-1/2 z-[60] w-[min(100%-2rem,24rem)] -translate-x-1/2 px-3"
        role="status"
        aria-live="polite"
    >
        <div
            class="pointer-events-auto rounded-xl border border-emerald-600/80 bg-emerald-950 px-4 py-3 text-center text-sm font-semibold text-emerald-50 shadow-lg dark:border-emerald-500 dark:bg-emerald-950 dark:text-emerald-50"
        >
            {{ snackbar.message }}
        </div>
    </div>

    <ConfigModal
        :master-generated-at="masterStore.generatedAt ?? ''"
        @added="onCartAdded"
        @close="() => {}"
    />

    <div
        v-if="showSettlementEdgeChips"
        class="fixed right-0 z-[48] flex flex-col gap-1.5 shadow-lg"
        :class="settlementChipsYClass"
    >
        <button
            type="button"
            class="flex h-14 w-14 items-center justify-center rounded-l-xl rounded-r-none border-y border-l-2 border-violet-600 bg-violet-900/95 text-xl text-violet-50 transition hover:bg-violet-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 dark:border-violet-500 dark:bg-violet-950 dark:text-violet-50 dark:hover:bg-violet-900"
            title="ADDITION"
            aria-label="ADDITION"
            @click="openAdditionBridge"
        >
            <span aria-hidden="true">🖨️</span>
        </button>
        <button
            type="button"
            class="flex h-14 w-14 items-center justify-center rounded-l-xl rounded-r-none border-y border-l-2 border-emerald-600 bg-emerald-800 text-xl text-white transition hover:bg-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 dark:border-emerald-500 dark:bg-emerald-900 dark:text-white dark:hover:bg-emerald-800"
            title="CLÔTURE"
            aria-label="CLÔTURE"
            @click="openClotureBridge"
        >
            <span aria-hidden="true">💰</span>
        </button>
    </div>

    <button
        v-if="showTakeoutCustomerLabelFab"
        type="button"
        class="fixed right-3 top-1/2 z-50 flex h-12 w-12 -translate-y-1/2 flex-col items-center justify-center rounded-2xl border-2 border-cyan-600 bg-cyan-700 text-cyan-50 shadow-lg transition hover:bg-cyan-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-cyan-400 focus-visible:ring-offset-2 focus-visible:ring-offset-slate-950 dark:border-cyan-500 dark:bg-cyan-800 dark:text-cyan-50 dark:hover:bg-cyan-700 dark:focus-visible:ring-offset-slate-950"
        :title="pos2Screen.takeoutFabTitle"
        :aria-label="pos2Screen.takeoutFabTitle"
        @click="openTakeoutLabelModal"
    >
        <span class="text-lg leading-none" aria-hidden="true">👤</span>
        <span class="mt-0.5 text-[9px] font-bold leading-none">{{ pos2Screen.takeoutFabLabel }}</span>
    </button>

    <Pos2LineDeletePinModal
        v-if="sessionUi.lineDeletePinChallenge"
        :open="true"
        :approvers="masterStore.pinApprovers"
        :labels="pos2Screen.lineDeletePin"
        @close="sessionUi.dismissLineDeletePinChallenge()"
        @submit="onLineDeletePinSubmit"
    />

    <TakeoutCustomerLabelModal
        :open="takeoutLabelModalOpen"
        :initial-name="takeoutLabelModalInitial.name"
        :initial-tel="takeoutLabelModalInitial.tel"
        :takeout-ui="pos2Screen.takeoutModal"
        @close="closeTakeoutLabelModal"
        @save="onTakeoutLabelSave"
    />

    <DebugPanel
        v-if="debugEnabled"
        :debug="debugStore"
        :master="masterStore"
        :cart="draftStore"
        :menu="menuStore"
    />
</template>

<style scoped>
/* 卓切替時のみ :key で再マウント → GPU 向き transform / filter の1回再生のみ */
.pos2-table-label-fx {
    animation: pos2-table-label-blink 0.38s ease-out 1;
}

.pos2-table-label-fx__beam {
    position: absolute;
    top: -5%;
    bottom: -5%;
    left: 0;
    width: 52%;
    transform: translateX(-105%) skewX(-14deg);
    background: linear-gradient(
        90deg,
        transparent 0%,
        rgba(255, 255, 255, 0.42) 48%,
        transparent 100%
    );
    animation: pos2-table-label-shine 0.58s ease-out 0.05s 1 both;
}

@keyframes pos2-table-label-shine {
    to {
        transform: translateX(240%) skewX(-14deg);
    }
}

@keyframes pos2-table-label-blink {
    0%,
    100% {
        opacity: 1;
        filter: brightness(1);
    }

    38% {
        opacity: 0.9;
        filter: brightness(1.14);
    }
}
</style>
