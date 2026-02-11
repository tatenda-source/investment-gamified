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

class PortfolioController extends Controller
{
    public function __construct(private readonly PortfolioService $portfolioService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $portfolio = Portfolio::with('stock')
            ->where('user_id', $request->user()->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $portfolio->map(fn (Portfolio $item): array => $this->transformPortfolioItem($item)),
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
