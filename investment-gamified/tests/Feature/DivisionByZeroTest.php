<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Stock;
use App\Models\Portfolio;

class DivisionByZeroTest extends TestCase
{
    public function test_profit_loss_percentage_is_safe_when_average_price_zero()
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['current_price' => 10]);

        Portfolio::create([
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'quantity' => 1,
            'average_price' => 0,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/portfolio');
        $response->assertStatus(200);
        $data = $response->json('data')[0] ?? null;
        $this->assertNotNull($data);
        $this->assertEquals(0, $data['profit_loss_percentage']);
    }
}
