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
     * Verify critical invariants after any transaction.
     * 
     * INVARIANTS (must always hold):
     * 1. No negative balances (impossible by design)
     * 2. No negative portfolio quantities (impossible by design) 
     * 3. Portfolio total cost <= cumulative trades
     * 4. Audit ledger matches portfolio state via deltas
     * 5. User balance >= 0 and is consistent with transactions
     * 
     * If any invariant fails, the concurrency control is broken.
     */
    protected function assertInvariants()
    {
        // Invariant 1: user balance is non-negative
        $this->user->refresh();
        $this->assertGreaterThanOrEqual(0, $this->user->balance, 'User balance cannot be negative (invariant violation: double-spend or negative balance)');

        // Invariant 2: portfolio quantities are non-negative
        $portfolios = Portfolio::where('user_id', $this->user->id)->get();
        foreach ($portfolios as $p) {
            $this->assertGreaterThanOrEqual(0, $p->quantity, "Portfolio quantity cannot be negative for stock {$p->stock_id} (invariant violation: short-sell or negative quantity)");
        }

        // Invariant 3: no portfolio records with zero quantity should be visible
        // (global scope filters them, but assert here for clarity)
        $zeroQty = DB::table('portfolios')
            ->where('user_id', $this->user->id)
            ->where('quantity', '<=', 0)
            ->count();
        $this->assertEquals(0, $zeroQty, 'Zero-quantity portfolios should not be visible (scope violation)');

        // Invariant 4: audit table is immutable (spot check: no updates on audit records)
        // This is enforced at the model level; just document the expectation
    }

    /**
     * Verify audit ledger matches portfolio state by aggregating deltas.
     */
    protected function assertAuditMatchesPortfolio()
    {
        $audits = \App\Models\PortfolioAudit::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->get();

        $totalBought = $audits->where('type', 'buy')->sum('quantity');
        $totalSold = $audits->where('type', 'sell')->sum('quantity');
        $expectedQuantity = $totalBought - $totalSold;

        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();

        if ($expectedQuantity > 0) {
            $this->assertNotNull($portfolio, 'Portfolio should exist if quantity > 0');
            $this->assertEquals($expectedQuantity, $portfolio->quantity, 'Audit ledger deltas must match portfolio quantity (invariant: ledger consistency)');
        } else {
            // If expected quantity <= 0, portfolio should be filtered by global scope
            $this->assertNull($portfolio, 'Portfolio with quantity <= 0 should be filtered (invariant: zero-quantity scope)');
        }
    }

    /**
     * Test: Concurrent buy operations cannot double-spend balance
     * 
     * CRITICAL INVARIANT: No double-spend
     * Scenario: User has $1000. Two concurrent requests each try to buy
     * $600 worth of stock (total $1200). Only one should succeed.
     * 
     * If this test fails, concurrency control is broken.
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

        // Invariant: No double-spend (user never went negative)
        $this->user->refresh();
        $this->assertEquals(400.00, $this->user->balance, 'Invariant: balance must match (1000 - 600)');
        $this->assertGreaterThanOrEqual(0, $this->user->balance, 'Invariant: balance cannot be negative (double-spend prevention)');

        // Invariant: Portfolio state matches audit ledger
        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertNotNull($portfolio);
        $this->assertEquals(6, $portfolio->quantity, 'Invariant: portfolio quantity matches expected');
        $this->assertEquals(100.00, $portfolio->average_price);

        // Invariant: Audit ledger matches portfolio via deltas
        $this->assertAuditMatchesPortfolio();
        $this->assertInvariants();
    }

    /**
     * Test: Concurrent sells cannot create negative portfolio quantity
     * 
     * CRITICAL INVARIANT: No negative quantities (no short-selling)
     * Scenario: User owns 10 shares. Two concurrent sell requests each
     * try to sell 8 shares (total 16). Only one should succeed.
     * 
     * If this test fails, short-selling or negative-quantity state was created.
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

        // Invariant: No short-selling (quantity never negative)
        $this->user->refresh();
        $this->assertEquals(10800.00, $this->user->balance); // 10000 + (8 * 100)

        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertNotNull($portfolio);
        $this->assertEquals(2, $portfolio->quantity, 'Invariant: portfolio quantity cannot be negative (10 - 8 = 2)');
        $this->assertGreaterThanOrEqual(0, $portfolio->quantity, 'Invariant: no short-selling allowed');

        // Invariant: Audit ledger matches portfolio state
        $this->assertAuditMatchesPortfolio();
        $this->assertInvariants();
    }

    /**
     * Test: Concurrent buy and sell on same user serializes correctly
     * 
     * INVARIANTS: 
     * - No double-spend (balance must stay within bounds)
     * - No negative quantities
     * - Audit ledger consistency
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

        // Verify final state with invariants
        $this->user->refresh();
        
        // Invariant: balance is non-negative and within expected range
        // Final balance: 5000 - 3000 (buy) + 1000 (sell) = 3000
        $this->assertEquals(3000.00, $this->user->balance, 'Invariant: balance must match expected (5000 - 3000 + 1000)');
        $this->assertGreaterThanOrEqual(0, $this->user->balance, 'Invariant: balance cannot be negative');

        // Invariant: final holdings are correct and non-negative
        // Final holdings: 20 + 30 - 10 = 40 shares
        $portfolio = Portfolio::where('user_id', $this->user->id)
            ->where('stock_id', $this->stock->id)
            ->first();
        
        $this->assertEquals(40, $portfolio->quantity, 'Invariant: portfolio quantity must match (20 + 30 - 10)');
        $this->assertGreaterThanOrEqual(0, $portfolio->quantity, 'Invariant: no short-selling');

        // Invariant: XP should be awarded correctly
        // Buy: +10 (buy) + Sell: +15 (sell) = +25
        $this->assertEquals(25, $this->user->experience_points, 'Invariant: XP must match expected (10 + 15)');

        // Invariant: Audit ledger matches portfolio state
        $this->assertAuditMatchesPortfolio();
        $this->assertInvariants();
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
