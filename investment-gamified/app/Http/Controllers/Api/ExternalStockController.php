<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\StockApiService;
use App\Services\FinancialModelingPrepService;
use App\Models\Stock;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

class ExternalStockController extends Controller
{
    protected StockApiService $alphaService;
    protected FinancialModelingPrepService $fmpService;

    public function __construct(StockApiService $alphaService, FinancialModelingPrepService $fmpService)
    {
        $this->alphaService = $alphaService;
        $this->fmpService = $fmpService;
    }

    /**
     * GET /api/external/stocks/quote/{symbol}?source=alphavantage|fmp
     */
    public function quote(Request $request, string $symbol)
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
            if ($source === 'fmp') {
                $data = $this->fmpService->getQuote($symbol);
            } else {
                $data = $this->alphaService->getQuote($symbol);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'External API error: ' . $e->getMessage()], 503);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No data returned from provider'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/history/{symbol}?source=alphavantage|fmp&days=30
     */
    public function history(Request $request, string $symbol)
    {
        $source = strtolower($request->query('source', 'alphavantage'));
        $days = (int) $request->query('days', 30);

        try {
            if ($source === 'fmp') {
                // fmp returns history as array of records
                $data = $this->fmpService->getHistoricalPrices($symbol, $days);
            } else {
                $data = $this->alphaService->getHistoricalData($symbol, 'compact');
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'External API error: ' . $e->getMessage()], 503);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No history available'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/search?q=apple&source=alphavantage|fmp
     */
    public function search(Request $request)
    {
        $query = $request->query('q');
        $source = strtolower($request->query('source', 'alphavantage'));

        if (!$query) {
            return response()->json(['success' => false, 'message' => 'Query parameter "q" is required'], 422);
        }

        try {
            if ($source === 'fmp') {
                $data = $this->fmpService->searchStocks($query);
            } else {
                $data = $this->alphaService->searchStocks($query);
            }
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'External API error: ' . $e->getMessage()], 503);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No results from provider'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    /**
     * GET /api/external/stocks/profile/{symbol}   (FMP only)
     */
    public function profile(string $symbol)
    {
        try {
            $data = $this->fmpService->getCompanyProfile($symbol);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'External API error: ' . $e->getMessage()], 503);
        }

        if (!$data) {
            return response()->json(['success' => false, 'message' => 'No profile data available'], 502);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }
}
