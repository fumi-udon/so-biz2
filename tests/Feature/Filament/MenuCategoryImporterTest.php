<?php

namespace Tests\Feature\Filament;

use App\Filament\Imports\MenuCategoryImporter;
use App\Models\MenuCategory;
use App\Models\Shop;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Filament\Concerns\MakesFilamentImportExportRecords;
use Tests\TestCase;

/**
 * C-3: MenuCategoryImporter が存在しない id を暗黙新規作成しないことの回帰防止。
 */
class MenuCategoryImporterTest extends TestCase
{
    use MakesFilamentImportExportRecords;
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $row
     */
    private function runMenuCategoryImport(Import $import, array $row): void
    {
        $importer = $import->getImporter(
            $this->identityImporterColumnMap(MenuCategoryImporter::class),
            [],
        );
        $importer($row);
    }

    /**
     * 存在しない id のカテゴリ行は RowImportFailedException で拒否され、件数が増えないことを検証する。
     */
    public function test_category_nonexistent_id_is_rejected(): void
    {
        $shop = Shop::query()->create([
            'name' => 'Cat Import Shop',
            'slug' => 'ci-'.bin2hex(random_bytes(4)),
            'is_active' => true,
        ]);
        $user = $this->createUserForFilament();
        $import = $this->createImportModel($user, MenuCategoryImporter::class);
        $before = MenuCategory::query()->count();

        try {
            $this->runMenuCategoryImport($import, [
                'id' => '88888',
                'shop_id' => (string) $shop->id,
                'name' => 'Phantom',
                'slug' => 'phantom-'.bin2hex(random_bytes(4)),
                'sort_order' => '0',
                'is_active' => '1',
            ]);
            $this->fail('RowImportFailedException が投げられるべき');
        } catch (RowImportFailedException $e) {
            $this->assertStringContainsString('指定された id = 88888 のカテゴリが存在しません', $e->getMessage());
        }

        $this->assertSame($before, MenuCategory::query()->count());
        $this->assertNull(MenuCategory::query()->find(88888));
    }
}
