<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Stock;
use App\Models\Portfolio;

class PortfolioIndexTest extends TestCase
{
    public function test_portfolio_index_paginates_and_computes_sql()
    {
        $user = User::factory()->create();
        $stock = Stock::factory()->create(['current_price' => 10]);

        // Create 60 portfolio rows for the user
        for ($i = 0; $i < 60; $i++) {
            Portfolio::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'quantity' => 1,
                'average_price' => 5,
            ]);
        }

        $this->actingAs($user, 'api')
             ->getJson('/api/portfolio?page=1&per_page=50')
             ->assertStatus(200)
             ->assertJsonPath('meta.per_page', 50)
             ->assertJsonPath('meta.total', 60)
             ->assertJsonStructure(['success', 'data', 'meta']);
    }
}
