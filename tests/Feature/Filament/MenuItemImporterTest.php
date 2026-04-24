<?php

namespace Tests\Feature\Filament;

use App\Filament\Exports\MenuItemExporter;
use App\Filament\Imports\MenuItemImporter;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Shop;
use App\Models\User;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Filament\Concerns\MakesFilamentImportExportRecords;
use Tests\TestCase;

/**
 * C-3: 存在しない id の Import 拒否、および id 空 / 既存 id 更新の回帰防止。
 * C-1 連携: Exporter 出力を Importer で再取り込みしたとき options_payload が保持されること。
 */
class MenuItemImporterTest extends TestCase
{
    use MakesFilamentImportExportRecords;
    use RefreshDatabase;

    /**
     * @return array{shop: Shop, category: MenuCategory, user: User}
     */
    private function seedShopAndCategory(): array
    {
        $shop = Shop::query()->create([
            'name' => 'Filament Import Shop',
            'slug' => 'fi-shop-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $category = MenuCategory::query()->create([
            'shop_id' => $shop->id,
            'name' => 'Mains',
            'slug' => 'mains-'.bin2hex(random_bytes(4)),
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $user = $this->createUserForFilament();

        return ['shop' => $shop, 'category' => $category, 'user' => $user];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function runMenuItemImport(Import $import, array $row): void
    {
        $importer = $import->getImporter(
            $this->identityImporterColumnMap(MenuItemImporter::class),
            [],
        );
        $importer($row);
    }

    /**
     * CSV の id が空のとき新規 MenuItem が 1 件増えることを検証する。
     */
    public function test_id_blank_creates_new_record(): void
    {
        $p = $this->seedShopAndCategory();
        $import = $this->createImportModel($p['user'], MenuItemImporter::class);

        $before = MenuItem::query()->count();

        $this->runMenuItemImport($import, [
            'shop_id' => (string) $p['shop']->id,
            'menu_category_id' => (string) $p['category']->id,
            'name' => 'New From CSV',
            'kitchen_name' => 'K',
            'slug' => 'new-from-csv-'.bin2hex(random_bytes(3)),
            'description' => '',
            'hero_image_path' => '',
            'from_price_minor' => '5000',
            'sort_order' => '0',
            'is_active' => '1',
            'allergy_note' => '',
            'dietary_slugs' => '',
            'options_payload' => '',
        ]);

        $this->assertSame($before + 1, MenuItem::query()->count());
        $this->assertTrue(
            MenuItem::query()->where('name', 'New From CSV')->exists()
        );
    }

    /**
     * CSV に既存 id を指定したとき、その行だけが更新され件数は変わらないことを検証する。
     */
    public function test_existing_id_updates_record(): void
    {
        $p = $this->seedShopAndCategory();
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'Before',
            'kitchen_name' => 'B',
            'slug' => 'before-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 1000,
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $import = $this->createImportModel($p['user'], MenuItemImporter::class);
        $countBefore = MenuItem::query()->count();

        $this->runMenuItemImport($import, [
            'id' => (string) $item->id,
            'shop_id' => (string) $p['shop']->id,
            'menu_category_id' => (string) $p['category']->id,
            'name' => 'After',
            'kitchen_name' => 'B',
            'slug' => $item->slug,
            'description' => '',
            'hero_image_path' => '',
            'from_price_minor' => '2000',
            'sort_order' => '0',
            'is_active' => '1',
            'allergy_note' => '',
            'dietary_slugs' => '',
            'options_payload' => '',
        ]);

        $this->assertSame($countBefore, MenuItem::query()->count());
        $this->assertSame('After', $item->fresh()->name);
        $this->assertSame(2000, (int) $item->fresh()->from_price_minor);
    }

    /**
     * DB に存在しない id を指定したとき RowImportFailedException で拒否され、件数が増えないことを検証する。
     */
    public function test_nonexistent_id_is_rejected(): void
    {
        $p = $this->seedShopAndCategory();
        $import = $this->createImportModel($p['user'], MenuItemImporter::class);
        $before = MenuItem::query()->count();

        try {
            $this->runMenuItemImport($import, [
                'id' => '99999',
                'shop_id' => (string) $p['shop']->id,
                'menu_category_id' => (string) $p['category']->id,
                'name' => 'Ghost',
                'kitchen_name' => '',
                'slug' => 'ghost-'.bin2hex(random_bytes(4)),
                'description' => '',
                'hero_image_path' => '',
                'from_price_minor' => '1000',
                'sort_order' => '0',
                'is_active' => '1',
                'allergy_note' => '',
                'dietary_slugs' => '',
                'options_payload' => '',
            ]);
            $this->fail('RowImportFailedException が投げられるべき');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('指定された id = 99999 の商品が存在しません', $e->getMessage());
        }

        $this->assertSame($before, MenuItem::query()->count());
        $this->assertNull(MenuItem::query()->find(99999));
    }

    /**
     * Exporter が出した options_payload セルを Importer で戻しても、rules/styles/toppings 構造が一致することを検証する（C-1 + Import 経路）。
     */
    public function test_options_payload_roundtrip(): void
    {
        $p = $this->seedShopAndCategory();
        $payload = [
            'rules' => ['style_required' => true],
            'styles' => [
                ['id' => '4-pieces', 'name' => '4 pcs', 'price_minor' => 12000],
            ],
            'toppings' => [
                ['id' => 'extra-sauce', 'name' => 'Sauce', 'price_delta_minor' => 500],
            ],
        ];
        $item = MenuItem::query()->create([
            'shop_id' => $p['shop']->id,
            'menu_category_id' => $p['category']->id,
            'name' => 'GYOZA GRILLÉS',
            'kitchen_name' => 'GZ',
            'slug' => 'gyoza-'.bin2hex(random_bytes(4)),
            'from_price_minor' => 12000,
            'sort_order' => 0,
            'is_active' => true,
            'options_payload' => $payload,
        ]);

        $export = $this->createExportModel($p['user'], MenuItemExporter::class);
        $exporter = $export->getExporter(
            $this->identityExporterColumnMap(MenuItemExporter::class),
            [],
        );
        $cells = $exporter($item);
        $headers = array_keys($this->identityExporterColumnMap(MenuItemExporter::class));
        $row = array_combine($headers, $cells);
        $this->assertIsArray($row);

        $import = $this->createImportModel($p['user'], MenuItemImporter::class);
        $row['id'] = (string) $item->id;
        $this->runMenuItemImport($import, $row);

        $fresh = $item->fresh();
        $this->assertIsArray($fresh->options_payload);
        $this->assertEquals($payload['rules'], $fresh->options_payload['rules']);
        $this->assertEquals($payload['styles'], $fresh->options_payload['styles']);
        $this->assertEquals($payload['toppings'], $fresh->options_payload['toppings']);
    }
}
