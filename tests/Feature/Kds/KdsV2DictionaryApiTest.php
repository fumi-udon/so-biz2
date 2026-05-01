<?php

declare(strict_types=1);

namespace Tests\Feature\Kds;

use App\Models\Shop;
use App\Support\KdsDictionarySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class KdsV2DictionaryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dictionary_returns_parsed_map_for_session_shop(): void
    {
        $shop = Shop::query()->create([
            'name' => 'Dict Shop',
            'slug' => 'dict-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);

        KdsDictionarySetting::saveText((int) $shop->id, "Extra Spicy:激辛\nFoo:bar");

        $this->withSession(['kds.active_shop_id' => (int) $shop->id])
            ->getJson(route('kds2.api.dictionary'))
            ->assertOk()
            ->assertExactJson([
                'extraspicy' => '激辛',
                'foo' => 'bar',
            ]);
    }

    public function test_dictionary_redirects_to_login_without_kds_session(): void
    {
        $this->get(route('kds2.api.dictionary'))
            ->assertRedirect(route('kds.login'));
    }
}
