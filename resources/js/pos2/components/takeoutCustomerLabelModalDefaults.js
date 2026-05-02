/**
 * defineProps の default は script setup 内のローカル宣言を参照できないため分離。
 * Object 型の既定は毎回新オブジェクトを返すファクトリ。
 */
export function createDefaultTakeoutUi() {
    return {
        title: '👤 Nom du client (à emporter)',
        hint: '📱 Affichage sur cette tablette seulement.',
        fieldName: 'Nom',
        fieldTel: '📞 Tél. (facultatif)',
        placeholderName: 'ex : Dupont',
        placeholderTel: '06 12 34 56 78',
        nameRequired: '⚠️ Indiquez un nom.',
        cancel: '✕ Annuler',
        save: '✓ OK',
    };
}
