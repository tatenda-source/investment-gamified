<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portfolio\BuyStockRequest;
use App\Http\Requests\Portfolio\SellStockRequest;
use App\Models\Portfolio;
use App\Services\PortfolioService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    public function index(Request $request): JsonResponse
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

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $portfolio = Portfolio::where('user_id', $user->id)->with('stock')->get();

        $totalValue = $portfolio->sum(fn (Portfolio $item): float => $item->quantity * $item->stock->current_price);
        $totalInvested = $portfolio->sum(fn (Portfolio $item): float => $item->quantity * $item->average_price);

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
            ],
        ]);
    }

    public function buyStock(BuyStockRequest $request): JsonResponse
    {
        return $this->handleTradeRequest($request, 'buyStock');
    }

    public function sellStock(SellStockRequest $request): JsonResponse
    {
        return $this->handleTradeRequest($request, 'sellStock');
    }

    private function handleTradeRequest(FormRequest $request, string $method): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $result = $this->portfolioService->{$method}($user, $validated['stock_symbol'], $validated['quantity']);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'data' => [
                'new_balance' => $user->fresh()->balance,
                'xp_earned' => $result['data']['xp_earned'] ?? null,
            ],
        ]);
    }

    private function transformPortfolioItem(Portfolio $item): array
    {
        $profitLoss = ($item->stock->current_price - $item->average_price) * $item->quantity;
        $profitLossPercentage = $item->average_price > 0
            ? (($item->stock->current_price - $item->average_price) / $item->average_price) * 100
            : 0;

        return [
            'stock_symbol' => $item->stock->symbol,
            'stock_name' => $item->stock->name,
            'quantity' => $item->quantity,
            'average_price' => $item->average_price,
            'current_price' => $item->stock->current_price,
            'total_value' => $item->quantity * $item->stock->current_price,
            'profit_loss' => $profitLoss,
            'profit_loss_percentage' => $profitLossPercentage,
        ];
    }
}
