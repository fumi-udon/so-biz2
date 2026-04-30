<?php

namespace Tests\Feature\Pos2;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\PosOrder;
use App\Models\Shop;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsPosDashboardFixtures;
use Tests\TestCase;

final class Pos2SessionSubmitDraftOrdersTest extends TestCase
{
    use BuildsPosDashboardFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    /**
     * @return array{0: MenuItem, 1: MenuItem}
     */
    private function twoMenuItems(Shop $shop): array
    {
        $category = MenuCategory::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Pos2 API Cat'],
            [
                'slug' => 'pos2-api-cat-'.Str::lower(Str::random(4)),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $a = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Pos2 Item A',
            'kitchen_name' => 'Pos2 Item A',
            'slug' => 'pos2-item-a-'.Str::lower(Str::random(4)),
            'from_price_minor' => 1_000,
            'sort_order' => 1,
            'is_active' => true,
            'options_payload' => ['styles' => [], 'toppings' => [], 'rules' => ['style_required' => false]],
        ]);

        $b = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Pos2 Item B',
            'kitchen_name' => 'Pos2 Item B',
            'slug' => 'pos2-item-b-'.Str::lower(Str::random(4)),
            'from_price_minor' => 2_000,
            'sort_order' => 2,
            'is_active' => true,
            'options_payload' => ['styles' => [], 'toppings' => [], 'rules' => ['style_required' => false]],
        ]);

        return [$a, $b];
    }

    public function test_submit_draft_returns_422_with_message_and_rolls_back_when_one_line_invalid(): void
    {
        $shop = $this->makeShop('pos2-draft-422');
        $table = $this->makeCustomerTable($shop, 16);
        $session = $this->openActiveSession($shop, $table);
        [$itemA] = $this->twoMenuItems($shop);

        $this->assertSame(0, PosOrder::query()->where('table_session_id', $session->id)->count());

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/api/sessions/'.$session->id.'/orders', [
            'client_submit_id' => 'test-client-uuid-1',
            'lines'            => [
                [
                    'product_id' => (string) $itemA->id,
                    'qty'        => 1,
                ],
                [
                    'product_id' => '999999999',
                    'qty'        => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $this->assertNotSame('', trim((string) $response->json('message')));

        $this->assertSame(
            0,
            PosOrder::query()->where('table_session_id', $session->id)->count(),
            'Transaction must roll back: no partial orders persisted.',
        );
    }

    public function test_submit_draft_returns_201_when_all_lines_valid(): void
    {
        $shop = $this->makeShop('pos2-draft-201');
        $table = $this->makeCustomerTable($shop, 17);
        $session = $this->openActiveSession($shop, $table);
        [$itemA, $itemB] = $this->twoMenuItems($shop);

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/api/sessions/'.$session->id.'/orders', [
            'client_submit_id' => 'test-client-uuid-2',
            'lines'            => [
                ['product_id' => (string) $itemA->id, 'qty' => 1],
                ['product_id' => (string) $itemB->id, 'qty' => 2],
            ],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['order_ids', 'session_revision', 'table_session_id']);
        $this->assertCount(2, $response->json('order_ids'));

        $this->assertSame(
            2,
            PosOrder::query()->where('table_session_id', $session->id)->count(),
        );
    }

    public function test_submit_draft_style_required_without_snapshot_returns_422(): void
    {
        $shop = $this->makeShop('pos2-style-req-422');
        $table = $this->makeCustomerTable($shop, 12);
        $session = $this->openActiveSession($shop, $table);

        $category = MenuCategory::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Pos2 Style Cat'],
            [
                'slug' => 'pos2-style-cat-'.Str::lower(Str::random(4)),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $styled = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Pos2 Styled Curry',
            'kitchen_name' => 'Pos2 Styled Curry',
            'slug' => 'pos2-styled-'.Str::lower(Str::random(4)),
            'from_price_minor' => 8_000,
            'sort_order' => 1,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => true],
                'styles' => [
                    ['id' => 'style-mild', 'name' => 'Mild', 'price_minor' => 8_000],
                    ['id' => 'style-hot', 'name' => 'Hot', 'price_minor' => 8_500],
                ],
                'toppings' => [],
            ],
        ]);

        $response = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/api/sessions/'.$session->id.'/orders', [
            'client_submit_id' => 'style-req-no-snap',
            'lines'            => [
                [
                    'product_id' => (string) $styled->id,
                    'qty'        => 1,
                ],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertNotSame('', trim((string) $response->json('message')));
        $this->assertSame(0, PosOrder::query()->where('table_session_id', $session->id)->count());
    }

    public function test_submit_draft_style_required_accepts_selected_option_snapshot_or_camel_alias(): void
    {
        $shop = $this->makeShop('pos2-style-req-201');
        $table = $this->makeCustomerTable($shop, 13);
        $session = $this->openActiveSession($shop, $table);

        $category = MenuCategory::query()->firstOrCreate(
            ['shop_id' => $shop->id, 'name' => 'Pos2 Style Cat2'],
            [
                'slug' => 'pos2-style-cat2-'.Str::lower(Str::random(4)),
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $styled = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Pos2 Styled Ramen',
            'kitchen_name' => 'Pos2 Styled Ramen',
            'slug' => 'pos2-styled-r-'.Str::lower(Str::random(4)),
            'from_price_minor' => 9_000,
            'sort_order' => 1,
            'is_active' => true,
            'options_payload' => [
                'rules' => ['style_required' => true],
                'styles' => [
                    ['id' => 'bowl-large', 'name' => 'Large', 'price_minor' => 9_500],
                ],
                'toppings' => [],
            ],
        ]);

        $snake = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/api/sessions/'.$session->id.'/orders', [
            'client_submit_id' => 'style-snake',
            'lines'            => [
                [
                    'product_id' => (string) $styled->id,
                    'qty'        => 1,
                    'selected_option_snapshot' => [
                        'id' => 'bowl-large',
                        'name' => 'Large',
                        'price_minor' => 9_500,
                    ],
                ],
            ],
        ]);
        $snake->assertStatus(201);

        $camel = $this->withSession([
            'pos2_authenticated' => true,
            'pos2.active_shop_id' => (int) $shop->id,
        ])->postJson('/pos2/api/sessions/'.$session->id.'/orders', [
            'client_submit_id' => 'style-camel',
            'lines'            => [
                [
                    'product_id' => (string) $styled->id,
                    'qty'        => 1,
                    'selectedOptionSnapshot' => [
                        'id' => 'bowl-large',
                        'name' => 'Large',
                        'price_minor' => 9_500,
                    ],
                ],
            ],
        ]);
        $camel->assertStatus(201);

        $this->assertSame(2, PosOrder::query()->where('table_session_id', $session->id)->count());
    }
}
