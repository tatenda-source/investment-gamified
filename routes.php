//routes /api.php
<?php
use App\http\Controllers\Api\PortfolioController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\AchievementController;

Route::middleware ('auth:sanctum')->group (function(){
    //portfolio endpoints 
    Route::get('/portfolio', [PortfolioController::class, 'index']);
    Route::get('/portfolio/summary', [PortfolioController::class, 'summary']);
    Route::post('/portfolio/buy', [PortfolioController::class, 'buyStock']);
    Route::post('/portfolio/sell', [PortfolioController::class, 'sellStock']);

    //stock endpoints 
    Route::get('/stocks', [StockController::class, 'index']);
    Route::get('/stocks/{symbol}', [StockController::class, 'show']);
    Route::get('/stocks/{symbol}/history', [StockController::class, 'history']);

    //gamification endpoints 
    Route::get('/achievements', [AchievementController::class, 'index']);
    Route::get('/leaderboard', [AchievementController::class, 'leaderboard']);
});

//app/Http/Controllers/Api/PortfolioController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Portfolio;
use App\Models\Stock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
class PortfolioController extends Controller
{
    Public function index(request$request){
        $portfolio = Portfolio::with ('stocks')->where('user_id', $request->user()->id)->get();
        return response()->json([
            'success' => true,
            'data' => $portfolio -> map(funtion($item){
                return [
                    'stock_symbol' => $item -> stock-> symbol,
                    'stock_name' => $item -> stock-> name,
                    'quantity' => $item -> quantity,
                    'average_price' => $item -> average_price,
                    'current_price' => $item -> stock-> current_price,
                    'total_value' => $item -> quantity * $item -> stock-> current_price,
                    'profit_loss' => ($item -> stock-> current_price - $item -> average_price) * $item -> quantity,
                    'profit_loss_percentage' => (($item -> stock-> current_price - $item -> average_price) / $item -> average_price) * 100,
                ];
            })
        ]);
    }
}

public function summary(Request $request)
    {
        $user = $request->user();
        $portfolio = Portfolio::where('user_id', $user->id)->with('stock')->get();
        
        $totalValue = $portfolio->sum(function ($item) {
            return $item->quantity * $item->stock->current_price;
        });
        
        $totalInvested = $portfolio->sum(function ($item) {
            return $item->quantity * $item->average_price;
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'balance' => $user->balance,
                'total_invested' => $totalInvested,
                'total_value' => $totalValue,
                'profit_loss' => $totalValue - $totalInvested,
                'profit_loss_percentage' => $totalInvested > 0 ? (($totalValue - $totalInvested) / $totalInvested) * 100 : 0,
                'level' => $user->level,
                'experience_points' => $user->experience_points,
                'next_level_xp' => $user->level * 1000,
            ]
        ]);
    }
//Handles buying a stock: validates input, checks balance, and prepares purchase for the authenticated user 

    public function buyStock(Request $request)
    {
        $validated = $request->validate([
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $stock = Stock::where('symbol', $validated['stock_symbol'])->first();
        $totalCost = $stock->current_price * $validated['quantity'];

        if ($user->balance < $totalCost) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient balance'
            ], 400);
        }


DB::transaction(function () use ($user, $stock, $validated, $totalCost) {
            // Deduct balance
            $user->balance -= $totalCost;
            $user->save();

            // Update or create portfolio entry
            $portfolio = Portfolio::firstOrNew([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
            ]);

            $newQuantity = $portfolio->quantity + $validated['quantity'];
            $portfolio->average_price = (($portfolio->average_price * $portfolio->quantity) + $totalCost) / $newQuantity;
            $portfolio->quantity = $newQuantity;
            $portfolio->save();

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'buy',
                'quantity' => $validated['quantity'],
                'price' => $stock->current_price,
                'total_amount' => $totalCost,
            ]);

            // Award XP
            $user->experience_points += 10;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock purchased successfully',
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => 10,
            ]
        ]);
    }

    public function sellStock(Request $request)
    {
        // Similar implementation for selling stocks
        $validated = $request->validate([
            'stock_symbol' => 'required|exists:stocks,symbol',
            'quantity' => 'required|integer|min:1',
        ]);

        $user = $request->user();
        $stock = Stock::where('symbol', $validated['stock_symbol'])->first();
        $portfolio = Portfolio::where('user_id', $user->id)->where('stock

       if (!$portfolio || $portfolio->quantity < $validated['quantity']) {
            return response()->json([
                'success' => false,
                'message' => 'Insufficient stock quantity'
            ], 400);
        }

   $totalRevenue = $stock->current_price * $validated['quantity'];

        DB::transaction(function () use ($user, $stock, $portfolio, $validated, $totalRevenue) {
            // Add to balance
            $user->balance += $totalRevenue;
            $user->save();

            // Update portfolio
            $portfolio->quantity -= $validated['quantity'];
            if ($portfolio->quantity == 0) {
                $portfolio->delete();
            } else {
                $portfolio->save();
            }

            // Record transaction
            Transaction::create([
                'user_id' => $user->id,
                'stock_id' => $stock->id,
                'type' => 'sell',
                'quantity' => $validated['quantity'],
                'price' => $stock->current_price,
                'total_amount' => $totalRevenue,
            ]);

            // Award XP
            $user->experience_points += 15;
            if ($user->experience_points >= $user->level * 1000) {
                $user->level++;
                $user->experience_points = 0;
            }
            $user->save();
        });

        return response()->json([
            'success' => true,
            'message' => 'Stock sold successfully',
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => 15,
            ]
        ]);
    }
}
