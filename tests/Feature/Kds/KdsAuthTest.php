<?php

namespace Tests\Feature\Kds;

use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class KdsAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_kds_dashboard_redirects_to_login_when_not_authenticated(): void
    {
        $this->get(route('kds.dashboard'))->assertRedirect(route('kds.login'));
    }

    public function test_login_with_valid_pin_redirects_to_dashboard_and_sets_session(): void
    {
        $shop = Shop::query()->create([
            'name' => 'PIN Shop',
            'slug' => 'pin-shop-'.bin2hex(random_bytes(3)),
            'is_active' => true,
        ]);

        Config::set('kds.pin_to_shop', ['4242' => (int) $shop->id]);

        $this->get(route('kds.login'));
        $this->post(route('kds.login.submit'), [
            'pin' => '4242',
            '_token' => csrf_token(),
        ])->assertRedirect(route('kds.dashboard'));

        $this->assertSame((int) $shop->id, session('kds.active_shop_id'));
    }

    public function test_login_with_invalid_pin_returns_validation_error(): void
    {
        Config::set('kds.pin_to_shop', ['4242' => 1]);

        $this->from(route('kds.login'))->post(route('kds.login.submit'), [
            'pin' => '0000',
            '_token' => csrf_token(),
        ])->assertSessionHasErrors('pin');
    }
}
