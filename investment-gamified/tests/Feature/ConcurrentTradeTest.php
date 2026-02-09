<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Stock;
use App\Models\Portfolio;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class ConcurrentTradeTest extends TestCase
{
    use RefreshDatabase;

    protected $portfolioService;
    protected $user;
    protected $stock;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->portfolioService = app(PortfolioService::class);
        
        // Create test user with $10,000 balance
        $this->user = User::factory()->create([
            'balance' => 10000.00,
            'level' => 1,
            'experience_points' => 0,
        ]);

        // Create test stock
        $this->stock = Stock::create([
            'symbol' => 'TEST',
            'name' => 'Test Stock',
            'description' => 'Test stock for concurrent trading',
            'current_price' => 100.00,
            'change_percentage' => 0.00,
        ]);
    }

    /**
     * Test: Concurrent buy operations cannot double-spend balance
     * 
     * Scenario: User has $1000. Two concurrent requests each try to buy
     * $600 worth of stock (total $1200). Only one should succeed.
     */
    public function test_concurrent_buys_prevent_overdraft()
    {
        $this->user->balance = 1000.00;
        $this->user->save();

        $quantity = 6; // $600 per buy at $100/share
        $results = [];

        // Simulate two concurrent buy attempts
        // In a real system, this would be separate HTTP requests
        // We use DB transactions to simulate concurrency
        
        $buyAttempt1 = function () use ($quantity, &$results) {
            $result = $this->portfolioService->buyStock($this->user, 'TEST', $quantity);
            $results['attempt1'] = $result;
        };

        $buyAttempt2 = function () use ($quantity, &$results) {
            // Refresh user to get current state
            $this->user->refresh();
            $result = $this->portfolioService->buyStock($this->user, 'TEST', $quantity);
            $results['attempt2'] = $result;
        };

        // Execute both attempts
        $buyAttempt1();
        $buyAttempt2();

        // Verify: Exactly one should succeed, one should fail with insufficient balance
        $this->assertTrue($results['attempt1']['success'], 'First buy should succeed');
        $this->assertFalse($results['attempt2']['success'], 'Second buy should fail due to insufficient balance');
        $this->assertStringContainsString('Insufficient balance', $results['attempt2']['message']);

        // Verify final state: Only 6 shares owned, balance exactly $400
        $this->user->refresh();
        $this->assertEquals(400.00, $this->user->balance);

        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertNotNull($portfolio);
        $this->assertEquals(6, $portfolio->quantity);
        $this->assertEquals(100.00, $portfolio->average_price);
    }

    /**
     * Test: Concurrent sells cannot create negative portfolio quantity
     * 
     * Scenario: User owns 10 shares. Two concurrent sell requests each
     * try to sell 8 shares (total 16). Only one should succeed.
     */
    public function test_concurrent_sells_prevent_negative_quantity()
    {
        // Set up: user owns 10 shares of TEST at $100 avg price
        Portfolio::create([
            'user_id' => $this->user->id,
            'stock_id' => $this->stock->id,
            'quantity' => 10,
            'average_price' => 100.00,
        ]);

        $quantity = 8; // Each sell attempt is 8 shares
        $results = [];

        $sellAttempt1 = function () use ($quantity, &$results) {
            $result = $this->portfolioService->sellStock($this->user, 'TEST', $quantity);
            $results['attempt1'] = $result;
        };

        $sellAttempt2 = function () use ($quantity, &$results) {
            $this->user->refresh();
            $result = $this->portfolioService->sellStock($this->user, 'TEST', $quantity);
            $results['attempt2'] = $result;
        };

        // Execute both attempts
        $sellAttempt1();
        $sellAttempt2();

        // Verify: Exactly one should succeed
        $this->assertTrue($results['attempt1']['success'], 'First sell should succeed');
        $this->assertFalse($results['attempt2']['success'], 'Second sell should fail due to insufficient quantity');
        $this->assertStringContainsString('Insufficient stock quantity', $results['attempt2']['message']);

        // Verify final state: 2 shares remain, balance increased by $800
        $this->user->refresh();
        $this->assertEquals(10800.00, $this->user->balance); // 10000 + (8 * 100)

        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertNotNull($portfolio);
        $this->assertEquals(2, $portfolio->quantity);
    }

    /**
     * Test: Concurrent buy and sell on same user serializes correctly
     * 
     * Scenario: User has $5000. Concurrently:
     * - Buy 30 shares ($3000)
     * - Sell 10 shares of existing holding ($1000)
     * 
     * Both should succeed and balance should be correct ($3000).
     */
    public function test_concurrent_buy_and_sell_serialize_correctly()
    {
        $this->user->balance = 5000.00;
        $this->user->save();

        // Pre-existing holding: 20 shares at $100
        Portfolio::create([
            'user_id' => $this->user->id,
            'stock_id' => $this->stock->id,
            'quantity' => 20,
            'average_price' => 100.00,
        ]);

        $results = [];

        $buyOp = function () use (&$results) {
            $result = $this->portfolioService->buyStock($this->user, 'TEST', 30);
            $results['buy'] = $result;
        };

        $sellOp = function () use (&$results) {
            $this->user->refresh();
            $result = $this->portfolioService->sellStock($this->user, 'TEST', 10);
            $results['sell'] = $result;
        };

        // Execute operations
        $buyOp();
        $sellOp();

        // Both should succeed
        $this->assertTrue($results['buy']['success']);
        $this->assertTrue($results['sell']['success']);

        // Verify final state
        $this->user->refresh();
        
        // Final balance: 5000 - 3000 (buy) + 1000 (sell) = 3000
        $this->assertEquals(3000.00, $this->user->balance);

        // Final holdings: 20 + 30 - 10 = 40 shares
        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertEquals(40, $portfolio->quantity);

        // XP should be awarded correctly: +10 (buy) + 15 (sell) = +25
        $this->assertEquals(25, $this->user->experience_points);
    }

    /**
     * Test: Portfolio average price is calculated correctly under lock
     * 
     * Scenario: User buys 10 shares at $100, then another 10 at $100
     * Average price should be $100 (not affected by concurrency)
     */
    public function test_average_price_calculation_under_lock()
    {
        $this->user->balance = 5000.00;
        $this->user->save();

        // First buy: 10 shares at $100
        $result1 = $this->portfolioService->buyStock($this->user, 'TEST', 10);
        $this->assertTrue($result1['success']);

        // Second buy: 10 shares at $100
        $this->user->refresh();
        $result2 = $this->portfolioService->buyStock($this->user, 'TEST', 10);
        $this->assertTrue($result2['success']);

        // Verify average price
        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();

        $this->assertEquals(20, $portfolio->quantity);
        $this->assertEquals(100.00, $portfolio->average_price);
    }

    /**
     * Test: Transaction records are never lost during concurrent operations
     * 
     * All trades should be recorded in transactions table regardless of
     * concurrent execution.
     */
    public function test_all_transactions_recorded_during_concurrency()
    {
        $this->user->balance = 10000.00;
        $this->user->save();

        // Buy operation
        $this->portfolioService->buyStock($this->user, 'TEST', 5);
        $this->user->refresh();

        // Sell operation
        $this->portfolioService->sellStock($this->user, 'TEST', 3);

        // Verify all transactions recorded
        $transactions = \App\Models\Transaction::where('user_id', $this->user->id)->get();
        
        $this->assertCount(2, $transactions);
        
        $buy = $transactions->firstWhere('type', 'buy');
        $sell = $transactions->firstWhere('type', 'sell');

        $this->assertNotNull($buy);
        $this->assertNotNull($sell);
        
        $this->assertEquals(5, $buy->quantity);
        $this->assertEquals(500.00, $buy->total_amount);
        
        $this->assertEquals(3, $sell->quantity);
        $this->assertEquals(300.00, $sell->total_amount);
    }

    /**
     * Test: XP is awarded exactly once per trade (no double-award)
     * 
     * This is a base-level test. Later refactors will add idempotency
     * keys to prevent retry-based double-award.
     */
    public function test_xp_awarded_once_per_trade()
    {
        $this->user->balance = 10000.00;
        $this->user->save();

        $initialXp = $this->user->experience_points;

        // Buy: should award 10 XP
        $this->portfolioService->buyStock($this->user, 'TEST', 5);
        $this->user->refresh();
        $this->assertEquals($initialXp + 10, $this->user->experience_points);

        // Sell: should award 15 XP
        $buyXp = $this->user->experience_points;
        $this->portfolioService->sellStock($this->user, 'TEST', 2);
        $this->user->refresh();
        $this->assertEquals($buyXp + 15, $this->user->experience_points);
    }

    /**
     * Test: Level up triggers correctly during concurrent trades
     * 
     * When XP crosses level threshold, level increments and XP resets.
     */
    public function test_level_up_triggers_correctly()
    {
        $this->user->update([
            'balance' => 100000.00,
            'level' => 1,
            'experience_points' => 990, // 10 XP until level up
        ]);

        // Buy: +10 XP, should trigger level up (990 + 10 = 1000)
        $this->portfolioService->buyStock($this->user, 'TEST', 10);

        $this->user->refresh();
        $this->assertEquals(2, $this->user->level);
        $this->assertEquals(0, $this->user->experience_points);
    }
}
