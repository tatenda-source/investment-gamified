<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Portfolio;
use App\Models\PortfolioAudit;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PortfolioService
{
    private const ERROR_INVALID_QUANTITY = 'Quantity must be greater than zero';
    private const ERROR_STOCK_NOT_FOUND = 'Stock not found';
    private const ERROR_INSUFFICIENT_BALANCE = 'Insufficient balance';
    private const ERROR_INSUFFICIENT_STOCK = 'Insufficient stock quantity';

    /**
     * Handle buying stocks for a user with pessimistic locking.
     * 
     * Pessimistic locking ensures that concurrent buy/sell operations
     * on the same user cannot create a race condition where balance
     * goes negative or portfolio becomes corrupt.
     * 
     * Returns array with keys: success (bool), message (string), data (array)
     * 
     * @param  \App\Models\User  $user
     * @param  string            $stockSymbol
     * @param  int               $quantity
     * @return array
     */
    public function buyStock(User $user, string $stockSymbol, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->errorResponse(self::ERROR_INVALID_QUANTITY);
        }

        $stock = $this->findStockBySymbol($stockSymbol);
        if ($stock === null) {
            return $this->errorResponse(self::ERROR_STOCK_NOT_FOUND);
        }

        $totalCost = $stock->current_price * $quantity;

        try {
            $result = DB::transaction(function () use ($user, $stock, $quantity, $totalCost) {
                // Acquire pessimistic lock on user row to prevent concurrent balance modifications.
                // This serializes buy/sell operations per user.
                $lockedUser = User::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // Check balance with locked row
                if ($lockedUser->balance < $totalCost) {
                    return $this->errorResponse(self::ERROR_INSUFFICIENT_BALANCE);
                }

                // Deduct balance on locked row
                $lockedUser->balance -= $totalCost;
                $lockedUser->save();

                // Lock and fetch portfolio entry to prevent concurrent edits
                $portfolio = Portfolio::where('user_id', $lockedUser->id)
                    ->where('stock_id', $stock->id)
                    ->lockForUpdate()
                    ->first();

                if ($portfolio === null) {
                    // Create new portfolio entry if it doesn't exist
                    $portfolio = new Portfolio([
                        'user_id' => $lockedUser->id,
                        'stock_id' => $stock->id,
                        'quantity' => 0,
                        'average_price' => 0,
                    ]);
                }

                $newQuantity = $portfolio->quantity + $quantity;
                $portfolio->average_price = (($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity;
                $portfolio->quantity = $newQuantity;
                $portfolio->save();

                // Record legacy transaction (backwards compatible)
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'stock_id' => $stock->id,
                    'type' => 'buy',
                    'quantity' => $quantity,
                    'price' => $stock->current_price,
                    'total_amount' => $totalCost,
                ]);

                $this->createAuditAndCheckpoint(
                    $lockedUser,
                    $stock,
                    $portfolio,
                    'buy',
                    $quantity,
                    $stock->current_price,
                    $totalCost,
                );

                // Award XP and check for level up
                $xpReward = config('game.xp.buy_stock', 10);
                $this->applyXpReward($lockedUser, $xpReward);

                return [
                    'success' => true,
                    'message' => 'Stock purchased successfully',
                    'data' => ['xp_earned' => $xpReward],
                ];
            });

            return $result;
        } catch (\Exception $e) {
            Log::error('Portfolio buy operation failed', [
                'user_id' => $user->id,
                'symbol' => $stockSymbol,
                'quantity' => $quantity,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    /**
     * Handle selling stocks for a user with pessimistic locking.
     * 
     * Pessimistic locking ensures that concurrent sell operations cannot:
     * - Allow selling more shares than owned
     * - Create negative portfolio quantities
     * - Create race condition in balance updates
     * 
     * @param  \App\Models\User  $user
     * @param  string            $stockSymbol
     * @param  int               $quantity
     * @return array
     */
    public function sellStock(User $user, string $stockSymbol, int $quantity): array
    {
        if ($quantity <= 0) {
            return $this->errorResponse(self::ERROR_INVALID_QUANTITY);
        }

        $stock = $this->findStockBySymbol($stockSymbol);
        if ($stock === null) {
            return $this->errorResponse(self::ERROR_STOCK_NOT_FOUND);
        }

        try {
            $result = DB::transaction(function () use ($user, $stock, $quantity) {
                // Acquire pessimistic lock on user row
                $lockedUser = User::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // Lock and fetch portfolio entry
                $portfolio = Portfolio::where('user_id', $lockedUser->id)
                    ->where('stock_id', $stock->id)
                    ->lockForUpdate()
                    ->first();

                // Validate ownership and quantity
                if (!$portfolio || $portfolio->quantity < $quantity) {
                    return $this->errorResponse(self::ERROR_INSUFFICIENT_STOCK);
                }

                $totalRevenue = $stock->current_price * $quantity;

                // Add proceeds to balance on locked row
                $lockedUser->balance += $totalRevenue;
                $lockedUser->save();

                // Update portfolio quantity on locked row. To keep a persistent
                // audit trail and to allow checksum/checkpointing we prefer to
                // keep portfolio rows (even with zero quantity) rather than
                // deleting them. This preserves the `ledger_checkpoint_id`
                // linkage and helps with rebuilds.
                $portfolio->quantity -= $quantity;
                if ($portfolio->quantity < 0) {
                    return $this->errorResponse(self::ERROR_INSUFFICIENT_STOCK);
                }
                // Persist zero quantities (do not delete)
                $portfolio->save();

                // Record legacy transaction (backwards compatible)
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'stock_id' => $stock->id,
                    'type' => 'sell',
                    'quantity' => $quantity,
                    'price' => $stock->current_price,
                    'total_amount' => $totalRevenue,
                ]);

                $this->createAuditAndCheckpoint(
                    $lockedUser,
                    $stock,
                    $portfolio,
                    'sell',
                    $quantity,
                    $stock->current_price,
                    $totalRevenue,
                );

                // Award XP and check for level up
                $xpReward = config('game.xp.sell_stock', 15);
                $this->applyXpReward($lockedUser, $xpReward);

                return [
                    'success' => true,
                    'message' => 'Stock sold successfully',
                    'data' => [
                        'proceeds' => $totalRevenue,
                        'xp_earned' => $xpReward,
                    ],
                ];
            });

            return $result;
        } catch (\Exception $e) {
            Log::error('Portfolio sell operation failed', [
                'user_id' => $user->id,
                'symbol' => $stockSymbol,
                'quantity' => $quantity,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function findStockBySymbol(string $stockSymbol): ?Stock
    {
        return Stock::where('symbol', $stockSymbol)->first();
    }

    private function errorResponse(string $message): array
    {
        return ['success' => false, 'message' => $message];
    }

    private function createAuditAndCheckpoint(
        User $user,
        Stock $stock,
        Portfolio $portfolio,
        string $type,
        int $quantity,
        float $price,
        float $totalAmount,
    ): void {
        $snapshot = [
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'portfolio' => [
                'quantity' => $portfolio->quantity,
                'average_price' => $portfolio->average_price,
            ],
            'user_balance' => $user->balance,
        ];

        $audit = PortfolioAudit::create([
            'user_id' => $user->id,
            'stock_id' => $stock->id,
            'type' => $type,
            'quantity' => $quantity,
            'price' => $price,
            'total_amount' => $totalAmount,
            'portfolio_snapshot' => json_encode($snapshot),
        ]);

        $portfolio->ledger_checkpoint_id = $audit->id;
        $portfolio->checksum = hash('sha256', $audit->portfolio_snapshot);
        $portfolio->save();
    }

    private function applyXpReward(User $user, int $xpReward): void
    {
        $user->experience_points += $xpReward;

        $levelUpThreshold = $user->level * config('game.xp.level_up_multiplier', 1000);
        if ($user->experience_points >= $levelUpThreshold) {
            $user->level++;
            $user->experience_points = 0;
        }

        $user->save();
    }
}
