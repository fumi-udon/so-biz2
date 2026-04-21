<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 標準スキャフォールドの smoke test。`/` ルートは Filament / staff 系モデルに
     * 触るため RefreshDatabase を有効化してスキーマを確保する。
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
