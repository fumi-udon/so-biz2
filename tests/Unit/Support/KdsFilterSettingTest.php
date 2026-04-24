<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\KdsFilterSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KdsFilterSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_keys_include_shop_id(): void
    {
        $this->assertSame('kds_7_kitchen_categories', KdsFilterSetting::kitchenKey(7));
        $this->assertSame('kds_7_hall_categories', KdsFilterSetting::hallKey(7));
    }

    public function test_is_category_filter_configured_false_when_empty(): void
    {
        $this->assertFalse(KdsFilterSetting::isCategoryFilterConfigured(1));
    }

    public function test_save_and_load_normalizes_ids(): void
    {
        KdsFilterSetting::saveKitchenCategoryIds(1, ['3', 5, 'x', 0, 5]);
        KdsFilterSetting::saveHallCategoryIds(1, []);

        $this->assertSame([3, 5], KdsFilterSetting::kitchenCategoryIds(1));
        $this->assertSame([], KdsFilterSetting::hallCategoryIds(1));
        $this->assertTrue(KdsFilterSetting::isCategoryFilterConfigured(1));

        $row = Setting::query()->where('key', 'kds_1_kitchen_categories')->sole();
        $this->assertSame([3, 5], $row->value);
    }
}
