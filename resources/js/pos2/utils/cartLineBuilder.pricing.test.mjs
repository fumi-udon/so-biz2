/**
 * cartLineBuilder 単価計算の単体テスト（Node 標準のみ、依存追加なし）
 *
 * 実行: npm run test:pos2-pricing
 * または: node resources/js/pos2/utils/cartLineBuilder.pricing.test.mjs
 */
import { buildCartLineSnapshot } from './cartLineBuilder.js';

function assertEqual(actual, expected, label) {
    if (actual !== expected) {
        throw new Error(`${label}: expected ${expected}, got ${actual}`);
    }
}

// RAMEN TOKYO: from 29 DT, style "Fruits de mer" = 39 DT full bowl price
const ramenTokyo = {
    id: 40,
    name: 'RAMEN TOKYO SAUCE SOJA',
    from_price_minor: 29000,
    menu_category_id: 1,
    options_payload: { rules: { style_required: true }, styles: [], toppings: [] },
};

const styleFruitsDeMer = { id: 'fruits-de-mer', name: 'Fruits de mer', price_minor: 39000 };
const stylePaiko = { id: 'paiko-poulet-frits', name: 'Paiko poulet frits', price_minor: 33000 };
const toppingSpicy = { id: 'spicy', name: 'Spicy', price_delta_minor: 1000 };
const toppingNori = { id: 'nori-emincees', name: 'Nori émincées', price_delta_minor: 2500 };

// 1) Fruits de mer + toppings → 39000 + 1000 + 2500 = 42500 (NOT 29000+39000+...)
{
    const line = buildCartLineSnapshot({
        masterItem: ramenTokyo,
        selectedOption: styleFruitsDeMer,
        selectedToppings: [toppingSpicy, toppingNori],
        qty: 1,
        masterGeneratedAt: 'test',
    });
    assertEqual(line.total_unit_price_minor, 42500, 'RAMEN Fruits de mer + 2 toppings');
    assertEqual(line.base_price_minor, 29000, 'base_price_minor は from を監査用に保持');
}

// 2) Paiko + Spicy + Nori → 33000 + 1000 + 2500 = 36500
{
    const line = buildCartLineSnapshot({
        masterItem: ramenTokyo,
        selectedOption: stylePaiko,
        selectedToppings: [toppingSpicy, toppingNori],
        qty: 1,
        masterGeneratedAt: 'test',
    });
    assertEqual(line.total_unit_price_minor, 36500, 'RAMEN Paiko + Spicy + Nori');
}

// 3) 単品（スタイルなし）: base + topping only
const simpleItem = {
    id: 2,
    name: 'TOFU CAPRESE',
    from_price_minor: 12000,
    menu_category_id: 5,
    options_payload: { rules: { style_required: false }, styles: [], toppings: [] },
};
{
    const line = buildCartLineSnapshot({
        masterItem: simpleItem,
        selectedOption: null,
        selectedToppings: [{ id: 'x', name: 'Extra', price_delta_minor: 500 }],
        qty: 1,
        masterGeneratedAt: 'test',
    });
    assertEqual(line.total_unit_price_minor, 12500, '単品 base + one topping delta');
}

// 4) 単品・トッピングなし
{
    const line = buildCartLineSnapshot({
        masterItem: simpleItem,
        selectedOption: null,
        selectedToppings: [],
        qty: 1,
        masterGeneratedAt: 'test',
    });
    assertEqual(line.total_unit_price_minor, 12000, '単品 toppings なし');
}

console.log('cartLineBuilder.pricing.test.mjs: OK (4 cases)');
