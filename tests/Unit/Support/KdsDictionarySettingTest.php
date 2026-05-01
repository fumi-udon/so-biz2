<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Models\Setting;
use App\Support\KdsDictionarySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KdsDictionarySettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_setting_key_and_json_cache_key_include_shop_id(): void
    {
        $this->assertSame('kds_7_dictionary_text', KdsDictionarySetting::key(7));
        $this->assertSame('kds2_dict_json_v2_7', KdsDictionarySetting::jsonCacheKey(7));
    }

    public function test_save_and_get_text_roundtrip(): void
    {
        $text = "Extra Spicy:激辛\nWakame:wkm";
        KdsDictionarySetting::saveText(1, $text);

        $this->assertSame($text, KdsDictionarySetting::getText(1));

        $row = Setting::query()->where('key', 'kds_1_dictionary_text')->sole();
        $this->assertSame($text, $row->value);
    }

    public function test_get_text_returns_empty_for_invalid_shop(): void
    {
        $this->assertSame('', KdsDictionarySetting::getText(0));
    }

    public function test_normalize_match_key_is_case_insensitive_and_strips_whitespace(): void
    {
        $this->assertSame('extraspicy', KdsDictionarySetting::normalizeMatchKey('Extra Spicy'));
        $this->assertSame('extraspicy', KdsDictionarySetting::normalizeMatchKey('  extra  spicy  '));
        $this->assertSame('spicy', KdsDictionarySetting::normalizeMatchKey('spiCy'));
        $this->assertSame('spicy', KdsDictionarySetting::normalizeMatchKey('s p i c y'));
    }
}
