<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Stock;
use App\Models\Portfolio;
use App\Models\PortfolioAudit;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ConcurrentLedgerTest extends TestCase
{
    use RefreshDatabase;

    protected $portfolioService;
    protected $user;
    protected $stock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->portfolioService = app(PortfolioService::class);

        $this->user = User::factory()->create([
            'balance' => 10000.00,
            'level' => 1,
            'experience_points' => 0,
        ]);

        $this->stock = Stock::create([
            'symbol' => 'LEDG',
            'name' => 'Ledger Test',
            'description' => 'Stock for ledger tests',
            'current_price' => 100.00,
            'change_percentage' => 0.00,
        ]);
    }

    public function test_buy_creates_portfolio_audit_entry()
    {
        $this->portfolioService->buyStock($this->user, 'LEDG', 5);

        $audit = PortfolioAudit::where('user_id', $this->user->id)->where('type', 'buy')->first();
        $this->assertNotNull($audit, 'Audit record should exist for buy');
        $this->assertEquals(5, $audit->quantity);
        $this->assertEquals(500.00, (float)$audit->total_amount);

        // Snapshot should parse and match portfolio state
        $snapshot = json_decode($audit->portfolio_snapshot, true);
        $this->assertEquals(5, $snapshot['portfolio']['quantity']);
    }

    public function test_sell_creates_portfolio_audit_entry()
    {
        Portfolio::create([
            'user_id' => $this->user->id,
            'stock_id' => $this->stock->id,
            'quantity' => 10,
            'average_price' => 100.00,
        ]);

        $this->portfolioService->sellStock($this->user, 'LEDG', 4);

        $audit = PortfolioAudit::where('user_id', $this->user->id)->where('type', 'sell')->first();
        $this->assertNotNull($audit, 'Audit record should exist for sell');
        $this->assertEquals(4, $audit->quantity);
    }

    public function test_ledger_entries_are_immutable()
    {
        $this->portfolioService->buyStock($this->user, 'LEDG', 2);
        $audit = PortfolioAudit::first();

        $this->expectException(\Illuminate\Database\QueryException::class);
        // Attempting to update should be prevented by DB trigger
        \DB::table('portfolio_audit')->where('id', $audit->id)->update(['quantity' => 999]);
    }

    public function test_portfolio_checksum_matches_snapshot()
    {
        $this->portfolioService->buyStock($this->user, 'LEDG', 3);
        $audit = PortfolioAudit::where('user_id', $this->user->id)->first();

        $portfolio = Portfolio::where('user_id', $this->user->id)->first();
        $this->assertNotNull($portfolio->checksum);

        $expected = hash('sha256', $audit->portfolio_snapshot);
        $this->assertEquals($expected, $portfolio->checksum);
    }

    public function test_rebuild_portfolio_from_ledger_matches_expected()
    {
        // Perform a sequence of ops
        $this->portfolioService->buyStock($this->user, 'LEDG', 10); // +10
        $this->portfolioService->sellStock($this->user, 'LEDG', 4); // -4
        $this->portfolioService->buyStock($this->user, 'LEDG', 2); // +2

        // Rebuild by aggregating ledger
        $buys = PortfolioAudit::where('user_id', $this->user->id)->where('type', 'buy')->sum('quantity');
        $sells = PortfolioAudit::where('user_id', $this->user->id)->where('type', 'sell')->sum('quantity');

        $expectedQuantity = $buys - $sells;

        $portfolio = Portfolio::where('user_id', $this->user->id)->first();
        $this->assertEquals($expectedQuantity, $portfolio->quantity);
    }

    public function test_zero_quantity_rejected()
    {
        $result = $this->portfolioService->buyStock($this->user, 'LEDG', 0);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Quantity must be greater than zero', $result['message']);
    }

    public function test_insufficient_funds_does_not_create_audit()
    {
        $this->user->balance = 100.00;
        $this->user->save();

        $result = $this->portfolioService->buyStock($this->user, 'LEDG', 5); // needs 500
        $this->assertFalse($result['success']);

        $audits = PortfolioAudit::where('user_id', $this->user->id)->get();
        $this->assertCount(0, $audits);
    }
}
