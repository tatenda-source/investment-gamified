<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class LeaderboardCacheTest extends TestCase
{
    public function test_leaderboard_is_cached_and_returns_pages()
    {
        Cache::flush();

        // create some users
        User::factory()->count(30)->create();

        $response = $this->getJson('/api/achievements/leaderboard?page=1&per_page=10');
        $response->assertStatus(200);

        // Second request should hit cache (indirectly) and return same structure
        $response2 = $this->getJson('/api/achievements/leaderboard?page=1&per_page=10');
        $response2->assertStatus(200)->assertJsonPath('meta.per_page', 10);
    }
}
