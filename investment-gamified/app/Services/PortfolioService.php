<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class PortfolioService
{
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
    public function buyStock($user, string $stockSymbol, int $quantity): array
    {
        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        $totalCost = $stock->current_price * $quantity;

        try {
            $result = DB::transaction(function () use ($user, $stock, $quantity, $totalCost) {
                // Acquire pessimistic lock on user row to prevent concurrent balance modifications.
                // This serializes buy/sell operations per user.
                $lockedUser = $user::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // Check balance with locked row
                if ($lockedUser->balance < $totalCost) {
                    return ['success' => false, 'message' => 'Insufficient balance'];
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

                // Record transaction (immutable log)
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'stock_id' => $stock->id,
                    'type' => 'buy',
                    'quantity' => $quantity,
                    'price' => $stock->current_price,
                    'total_amount' => $totalCost,
                ]);

                // Award XP and check for level up
                $lockedUser->experience_points += 10;
                if ($lockedUser->experience_points >= $lockedUser->level * 1000) {
                    $lockedUser->level++;
                    $lockedUser->experience_points = 0;
                }
                $lockedUser->save();

                return [
                    'success' => true,
                    'message' => 'Stock purchased successfully',
                    'data' => ['xp_earned' => 10],
                ];
            });

            return $result;
        } catch (\Exception $e) {
            // Catch deadlock or other transaction failures
            \Log::error('Portfolio buy operation failed', [
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
    public function sellStock($user, string $stockSymbol, int $quantity): array
    {
        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        try {
            $result = DB::transaction(function () use ($user, $stock, $quantity) {
                // Acquire pessimistic lock on user row
                $lockedUser = $user::where('id', $user->id)
                    ->lockForUpdate()
                    ->first();

                // Lock and fetch portfolio entry
                $portfolio = Portfolio::where('user_id', $lockedUser->id)
                    ->where('stock_id', $stock->id)
                    ->lockForUpdate()
                    ->first();

                // Validate ownership and quantity
                if (!$portfolio || $portfolio->quantity < $quantity) {
                    return ['success' => false, 'message' => 'Insufficient stock quantity'];
                }

                $totalRevenue = $stock->current_price * $quantity;

                // Add proceeds to balance on locked row
                $lockedUser->balance += $totalRevenue;
                $lockedUser->save();

                // Update portfolio quantity on locked row
                $portfolio->quantity -= $quantity;
                if ($portfolio->quantity == 0) {
                    $portfolio->delete();
                } else {
                    $portfolio->save();
                }

                // Record transaction (immutable log)
                Transaction::create([
                    'user_id' => $lockedUser->id,
                    'stock_id' => $stock->id,
                    'type' => 'sell',
                    'quantity' => $quantity,
                    'price' => $stock->current_price,
                    'total_amount' => $totalRevenue,
                ]);

                // Award XP and check for level up
                $lockedUser->experience_points += 15;
                if ($lockedUser->experience_points >= $lockedUser->level * 1000) {
                    $lockedUser->level++;
                    $lockedUser->experience_points = 0;
                }
                $lockedUser->save();

                return [
                    'success' => true,
                    'message' => 'Stock sold successfully',
                    'data' => [
                        'proceeds' => $totalRevenue,
                        'xp_earned' => 15,
                    ],
                ];
            });

            return $result;
        } catch (\Exception $e) {
            // Catch deadlock or other transaction failures
            \Log::error('Portfolio sell operation failed', [
                'user_id' => $user->id,
                'symbol' => $stockSymbol,
                'quantity' => $quantity,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
