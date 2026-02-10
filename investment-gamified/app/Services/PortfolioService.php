<?php

namespace App\Services;

use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\Transaction;
use App\Models\PortfolioAudit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

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
        // Guard against invalid quantities
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than zero'];
        }

        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        $totalCost = $stock->current_price * $quantity;

        $xpConfig = Config::get('gamification.xp.buy_reward', 10);
        $baseXp = Config::get('gamification.level_up.base_xp', 1000);

        $attempts = 0;
        while ($attempts < 3) {
            $attempts++;
            try {
                $result = DB::transaction(function () use ($user, $stock, $quantity, $totalCost, $xpConfig, $baseXp) {
                    // Read user row to get current version for optimistic update
                    $current = DB::table('users')->where('id', $user->id)->first(['id', 'balance', 'experience_points', 'level', 'balance_version']);
                    if (!$current) {
                        return ['success' => false, 'message' => 'User not found'];
                    }

                    if ($current->balance < $totalCost) {
                        return ['success' => false, 'message' => 'Insufficient balance'];
                    }

                    $cv = (int) ($current->balance_version ?? 1);

                    // Compute SQL expressions for xp and level rollover using baseXp
                    $xp = (int) $xpConfig;

                    $levelIncExpr = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN 1 ELSE 0 END";
                    $xpNewExpr = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN (experience_points + {$xp}) - (level * {$baseXp}) ELSE experience_points + {$xp} END";

                    $updated = DB::table('users')
                        ->where('id', $user->id)
                        ->where('balance_version', $cv)
                        ->where('balance', '>=', $totalCost)
                        ->update([
                            'balance' => DB::raw("balance - {$totalCost}"),
                            'experience_points' => DB::raw($xpNewExpr),
                            'level' => DB::raw("level + ({$levelIncExpr})"),
                            'balance_version' => DB::raw('balance_version + 1'),
                        ]);

                    if ($updated === 0) {
                        // Concurrent modification; let outer loop retry
                        throw new \Exception('Concurrency conflict updating user balance');
                    }

                    // Upsert portfolio row for this user/stock
                    $portfolio = Portfolio::where('user_id', $user->id)
                        ->where('stock_id', $stock->id)
                        ->first();

                    if (!$portfolio) {
                        $portfolio = new Portfolio([
                            'user_id' => $user->id,
                            'stock_id' => $stock->id,
                            'quantity' => 0,
                            'average_price' => 0,
                        ]);
                    }

                    $newQuantity = $portfolio->quantity + $quantity;
                    $portfolio->average_price = $newQuantity > 0 ? ((($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity) : 0;
                    $portfolio->quantity = $newQuantity;
                    $portfolio->save();

                    // Legacy transaction
                    Transaction::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'buy',
                        'quantity' => $quantity,
                        'price' => $stock->current_price,
                        'total_amount' => $totalCost,
                    ]);

                    // Audit (store delta instead of full snapshot to reduce growth)
                    $audit = PortfolioAudit::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'buy',
                        'quantity' => $quantity,
                        'price' => $stock->current_price,
                        'total_amount' => $totalCost,
                        'portfolio_snapshot' => json_encode([
                            'quantity_change' => $quantity,
                            'average_price_after' => $portfolio->average_price,
                        ]),
                    ]);

                    $checksum = hash('sha256', $audit->portfolio_snapshot);
                    $portfolio->ledger_checkpoint_id = $audit->id;
                    $portfolio->checksum = $checksum;
                    $portfolio->save();

                    // Invalidate leaderboard cache because XP changed
                    try {
                        Cache::tags(['leaderboard'])->flush();
                    } catch (\Exception $e) {
                        // Some cache drivers do not support tags; ignore gracefully
                        \Log::debug('Cache tags not supported for leaderboard flush');
                    }

                    return [
                        'success' => true,
                        'message' => 'Stock purchased successfully',
                        'data' => ['xp_earned' => $xp],
                    ];
                });

                return $result;
            } catch (\Exception $e) {
                // If concurrency conflict try again with exponential backoff
                if ($attempts < 3) {
                    usleep(rand(50, 200) * 1000);
                    continue;
                }

                \Log::error('Portfolio buy operation failed', [
                    'user_id' => $user->id,
                    'symbol' => $stockSymbol,
                    'quantity' => $quantity,
                    'exception' => $e->getMessage(),
                ]);
                throw $e;
            }
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
        // Guard against invalid quantities
        if ($quantity <= 0) {
            return ['success' => false, 'message' => 'Quantity must be greater than zero'];
        }

        $stock = Stock::where('symbol', $stockSymbol)->first();
        if (!$stock) {
            return ['success' => false, 'message' => 'Stock not found'];
        }

        $xpConfig = Config::get('gamification.xp.sell_reward', 15);
        $baseXp = Config::get('gamification.level_up.base_xp', 1000);

        $attempts = 0;
        while ($attempts < 3) {
            $attempts++;
            try {
                $result = DB::transaction(function () use ($user, $stock, $quantity, $xpConfig, $baseXp) {
                    $portfolio = Portfolio::where('user_id', $user->id)
                        ->where('stock_id', $stock->id)
                        ->first();

                    if (!$portfolio || $portfolio->quantity < $quantity) {
                        return ['success' => false, 'message' => 'Insufficient stock quantity'];
                    }

                    $totalRevenue = $stock->current_price * $quantity;

                    // Update user atomically: increment balance and xp, bump level if threshold hit
                    $current = DB::table('users')->where('id', $user->id)->first(['balance_version', 'experience_points', 'level']);
                    $cv = (int) ($current->balance_version ?? 1);
                    $xp = (int) $xpConfig;

                    $levelIncExpr = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN 1 ELSE 0 END";
                    $xpNewExpr = "CASE WHEN (experience_points + {$xp}) >= (level * {$baseXp}) THEN (experience_points + {$xp}) - (level * {$baseXp}) ELSE experience_points + {$xp} END";

                    $updated = DB::table('users')
                        ->where('id', $user->id)
                        ->where('balance_version', $cv)
                        ->update([
                            'balance' => DB::raw("balance + {$totalRevenue}"),
                            'experience_points' => DB::raw($xpNewExpr),
                            'level' => DB::raw("level + ({$levelIncExpr})"),
                            'balance_version' => DB::raw('balance_version + 1'),
                        ]);

                    if ($updated === 0) {
                        throw new \Exception('Concurrency conflict updating user balance');
                    }

                    // update portfolio quantity
                    $portfolio->quantity -= $quantity;
                    if ($portfolio->quantity < 0) {
                        return ['success' => false, 'message' => 'Insufficient stock quantity'];
                    }
                    $portfolio->save();

                    Transaction::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'sell',
                        'quantity' => $quantity,
                        'price' => $stock->current_price,
                        'total_amount' => $totalRevenue,
                    ]);

                    $audit = PortfolioAudit::create([
                        'user_id' => $user->id,
                        'stock_id' => $stock->id,
                        'type' => 'sell',
                        'quantity' => $quantity,
                        'price' => $stock->current_price,
                        'total_amount' => $totalRevenue,
                        'portfolio_snapshot' => json_encode(['quantity_change' => -$quantity, 'average_price_after' => $portfolio->average_price]),
                    ]);

                    $portfolio->ledger_checkpoint_id = $audit->id;
                    $portfolio->checksum = hash('sha256', $audit->portfolio_snapshot);
                    $portfolio->save();

                    try {
                        Cache::tags(['leaderboard'])->flush();
                    } catch (\Exception $e) {
                        \Log::debug('Cache tags not supported for leaderboard flush');
                    }

                    return ['success' => true, 'message' => 'Stock sold successfully', 'data' => ['proceeds' => $totalRevenue, 'xp_earned' => $xp]];
                });

                return $result;
            } catch (\Exception $e) {
                if ($attempts < 3) {
                    usleep(rand(50, 200) * 1000);
                    continue;
                }

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
}
