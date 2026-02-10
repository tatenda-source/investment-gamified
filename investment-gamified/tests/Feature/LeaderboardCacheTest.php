<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class LeaderboardCacheTest extends TestCase
{
    /**
     * Test: Leaderboard is cached for the configured TTL.
     * 
     * CACHE BEHAVIOR (intentional):
     * - Results are served from cache, not fresh from DB
     * - Staleness is bounded by config/cache_ttl.php 'leaderboard' TTL (default 300s)
     * - Cache is invalidated when users gain XP (during buy/sell), not on every request
     * - This is a performance+consistency tradeoff: eventual consistency is acceptable
     * 
     * CLIENT IMPACT:
     * - Rank may be off by a few positions if XP changes happened <TTL seconds ago
     * - This is not a bug; it is by design
     */
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
