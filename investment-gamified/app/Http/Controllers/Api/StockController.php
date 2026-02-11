<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Stock;
use App\Models\StockHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $stocks = Stock::query()
            ->when($request->category, function ($query, $category): void {
                $query->where('category', $category);
            })
            ->get();

        return response()->json([
            'success' => true,
            'data' => $stocks->map(fn (Stock $stock): array => $this->transformStock($stock, false)),
        ]);
    }

    public function show(string $symbol): JsonResponse
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $this->transformStock($stock, true),
        ]);
    }

    public function history(string $symbol, Request $request): JsonResponse
    {
        $stock = Stock::where('symbol', $symbol)->firstOrFail();
        $days = (int) $request->input('days', 30);

        $history = StockHistory::where('stock_id', $stock->id)
            ->where('date', '>=', now()->subDays($days))
            ->orderBy('date', 'asc')
            ->get(['date', 'close_price']);

        return response()->json([
            'success' => true,
            'data' => $history,
        ]);
    }

    private function transformStock(Stock $stock, bool $withFunFact): array
    {
        $payload = [
            'symbol' => $stock->symbol,
            'name' => $stock->name,
            'current_price' => $stock->current_price,
            'change_percentage' => $stock->change_percentage,
            'category' => $stock->category,
            'description' => $stock->description,
            'kid_friendly_description' => $stock->kid_friendly_description,
        ];

        if ($withFunFact) {
            $payload['fun_fact'] = $stock->fun_fact;
        }

        return $payload;
    }
}
