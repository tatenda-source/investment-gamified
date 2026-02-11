<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FinancialModelingPrepService;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ExternalStockController extends Controller
{
    public function __construct(
        private readonly StockApiService $alphaService,
        private readonly FinancialModelingPrepService $fmpService,
    ) {
    }

    /**
     * GET /api/external/stocks/quote/{symbol}?source=alphavantage|fmp
     */
    public function quote(Request $request, string $symbol): JsonResponse
    {
        $symbol = strtoupper(trim($symbol));

        // Validate symbol format early
        if (! preg_match('/^[A-Z0-9\.\-]{1,8}$/', $symbol)) {
            return response()->json(['success' => false, 'message' => 'Invalid stock symbol format'], 422);
        }

        // Ensure symbol exists in our stocks table to prevent abuse
        if (! Stock::where('symbol', $symbol)->exists()) {
            return response()->json(['success' => false, 'message' => 'Symbol not available for trading'], 404);
        }

        $source = strtolower($request->query('source', 'alphavantage'));

        try {
            $data = $source === 'fmp'
                ? $this->fmpService->getQuote($symbol)
                : $this->alphaService->getQuote($symbol);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No data returned from provider');
    }

    /**
     * GET /api/external/stocks/history/{symbol}?source=alphavantage|fmp&days=30
     */
    public function history(Request $request, string $symbol): JsonResponse
    {
        $source = $this->resolveSource($request);
        $days = (int) $request->query('days', 30);

        try {
            $data = $source === 'fmp'
                ? $this->fmpService->getHistoricalPrices($symbol, $days)
                : $this->alphaService->getHistoricalData($symbol, 'compact');
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No history available');
    }

    /**
     * GET /api/external/stocks/search?q=apple&source=alphavantage|fmp
     */
    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');
        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Query parameter "q" is required'], 422);
        }

        $source = $this->resolveSource($request);

        try {
            $data = $source === 'fmp'
                ? $this->fmpService->searchStocks($query)
                : $this->alphaService->searchStocks($query);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No results from provider');
    }

    /**
     * GET /api/external/stocks/profile/{symbol}   (FMP only)
     */
    public function profile(string $symbol): JsonResponse
    {
        try {
            $data = $this->fmpService->getCompanyProfile($symbol);
        } catch (\Exception $e) {
            return $this->externalApiErrorResponse($e->getMessage());
        }

        return $this->providerDataResponse($data, 'No profile data available');
    }

    private function resolveSource(Request $request): string
    {
        return strtolower((string) $request->query('source', 'alphavantage'));
    }

    private function externalApiErrorResponse(string $error): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'External API error: ' . $error,
        ], 503);
    }

    private function providerDataResponse(?array $data, string $emptyMessage): JsonResponse
    {
        if (!$data) {
            return response()->json(['success' => false, 'message' => $emptyMessage], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
