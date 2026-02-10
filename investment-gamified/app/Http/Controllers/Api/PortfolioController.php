<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PortfolioService;
use App\Models\Portfolio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    protected $portfolioService;

    public function __construct(PortfolioService $portfolioService)
    {
        $this->portfolioService = $portfolioService;
    }

    public function index(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 50)));

        // Database-level projection: join portfolios -> stocks and compute aggregates in SQL.
        $query = DB::table('portfolios as p')
            ->join('stocks as s', 'p.stock_id', '=', 's.id')
            ->where('p.user_id', $request->user()->id)
            ->where('p.quantity', '>', 0)
            ->select(
                'p.id as portfolio_id',
                's.id as stock_id',
                's.symbol as stock_symbol',
                's.name as stock_name',
                'p.quantity',
                'p.average_price',
                's.current_price',
                DB::raw('p.quantity * s.current_price as total_value'),
                DB::raw('(s.current_price - p.average_price) * p.quantity as profit_loss'),
                DB::raw('CASE WHEN p.average_price = 0 THEN 0 ELSE ((s.current_price - p.average_price) / p.average_price) * 100 END as profit_loss_percentage')
            );

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'success' => true,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
            ],
        ]);
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
                'name' => $user->name,
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

    public function buyStock(\App\Http\Requests\Portfolio\BuyStockRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();

        $result = $this->portfolioService->buyStock($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => $result['data']['xp_earned'] ?? null,
            ]
        ]);
    }

    public function sellStock(\App\Http\Requests\Portfolio\SellStockRequest $request)
    {
        $validated = $request->validated();

        $user = $request->user();

        $result = $this->portfolioService->sellStock($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json(['success' => false, 'message' => $result['message']], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => $result['data']['xp_earned'] ?? null,
            ]
        ]);
    }
}
