<?php

namespace Tests\Feature\Filament;

use App\Filament\Exports\MenuItemExporter;
use App\Models\DietaryBadge;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Filament\Concerns\MakesFilamentImportExportRecords;
use Tests\TestCase;

/**
 * C-1: options_payload が CSV 用セルとして壊れず有効な JSON になること、
 * dietary_slugs がカンマ区切りで出力されることの回帰防止。
 */
class MenuItemExporterTest extends TestCase
{
    use MakesFilamentImportExportRecords;
    use RefreshDatabase;

    /**
     * options_payload を json_decode できる単一オブジェクトであり、
     * 監査で問題になった「トップレベル値のカンマ連結」形式になっていないことを検証する。
     */
    public function test_options_payload_is_valid_json_in_csv(): void
    {
        $shop = Shop::query()->create([
            'name' => 'Export Shop',
            'slug' => 'ex-shop-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Cat',
            'slug' => 'cat-'.bin2hex(random_bytes(4)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $payload = [
            'rules' => ['style_required' => true],
            'styles' => [
                ['id' => 'm', 'name' => 'M', 'price_minor' => 1000],
            ],
            'toppings' => [
                ['id' => 'x', 'name' => 'X', 'price_delta_minor' => 0],
            ],
        ];
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Probe',
            'kitchen_name' => 'P',
            'slug' => 'probe-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => $payload,
        ]);

        $user = $this->createUserForFilament();
        $export = $this->createExportModel($user, MenuItemExporter::class);
        $exporter = $export->getExporter(
            $this->identityExporterColumnMap(MenuItemExporter::class),
            [],
        );
        $cells = $exporter($item);
        $headers = array_keys($this->identityExporterColumnMap(MenuItemExporter::class));
        $row = array_combine($headers, $cells);
        $this->assertIsArray($row);

        $cell = $row['options_payload'];
        $this->assertIsString($cell);
        $this->assertStringStartsWith('{', $cell);
        $this->assertDoesNotMatchRegularExpression('/^\{[^}]*\},\s*\[/', $cell,
            '壊れた形式（先頭オブジェクト直後の "}, [" パターン）であってはならない');

        $decoded = json_decode($cell, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('rules', $decoded);
        $this->assertArrayHasKey('styles', $decoded);
        $this->assertArrayHasKey('toppings', $decoded);
        $this->assertEquals($payload['rules'], $decoded['rules']);
        $this->assertEquals($payload['styles'], $decoded['styles']);
        $this->assertEquals($payload['toppings'], $decoded['toppings']);
    }

    /**
     * DietaryBadge を付けた商品の dietary_slugs 列がカンマ区切りスラッグになることを検証する。
     */
    public function test_dietary_slugs_is_comma_separated(): void
    {
        $shop = Shop::query()->create([
            'name' => 'Export Shop 2',
            'slug' => 'ex2-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Cat',
            'slug' => 'cat2-'.bin2hex(random_bytes(4)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $slugVegan = 'vegan-'.bin2hex(random_bytes(3));
        $slugGluten = 'gluten-free-'.bin2hex(random_bytes(3));
        $b1 = DietaryBadge::query()->create([
            'shop_id' => $shop->id,
            'slug' => $slugVegan,
            'name' => 'Vegan',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $b2 = DietaryBadge::query()->create([
            'shop_id' => $shop->id,
            'slug' => $slugGluten,
            'name' => 'GF',
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $item = MenuItem::query()->create([
            'shop_id' => $shop->id,
            'menu_category_id' => $category->id,
            'name' => 'Tagged',
            'kitchen_name' => 'T',
            'slug' => 'tagged-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 0,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $item->dietaryBadges()->sync([
            $b1->id => ['sort_order' => 0],
            $b2->id => ['sort_order' => 1],
        ]);
        $item->load('dietaryBadges');

        $user = $this->createUserForFilament();
        $export = $this->createExportModel($user, MenuItemExporter::class);
        $exporter = $export->getExporter(
            $this->identityExporterColumnMap(MenuItemExporter::class),
            [],
        );
        $cells = $exporter($item);
        $headers = array_keys($this->identityExporterColumnMap(MenuItemExporter::class));
        $row = array_combine($headers, $cells);
        $this->assertIsArray($row);

        $this->assertSame(
            $slugVegan.','.$slugGluten,
            $row['dietary_slugs']
        );
    }
}
