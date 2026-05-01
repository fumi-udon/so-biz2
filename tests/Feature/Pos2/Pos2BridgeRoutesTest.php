<?php

namespace Tests\Feature\Pos2;

use Tests\TestCase;

final class Pos2BridgeRoutesTest extends TestCase
{
    public function test_bridge_routes_redirect_to_pos2_login_when_unauthenticated(): void
    {
        $this->get('/pos2/bridge/sessions/1/addition')
            ->assertRedirect(route('pos2.login'));

        $this->get('/pos2/bridge/sessions/1/cloture')
            ->assertRedirect(route('pos2.login'));

        $this->get('/pos2/bridge/sessions/1/receipt')
            ->assertRedirect(route('pos2.login'));
    }
}
