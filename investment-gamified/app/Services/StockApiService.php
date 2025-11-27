<?php
// app/Services/StockApiService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class StockApiService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://www.alphavantage.co/query';

    public function __construct()
    {
        $this->apiKey = config('services.alphavantage.key');
    }

    /**
     * Get real-time stock quote
     */
    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "stock_quote_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol) {
            try {
                $response = Http::get($this->baseUrl, [
                    'function' => 'GLOBAL_QUOTE',
                    'symbol' => $symbol,
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['Global Quote'])) {
                        $quote = $data['Global Quote'];
                        return [
                            'symbol' => $quote['01. symbol'] ?? null,
                            'price' => $quote['05. price'] ?? null,
                            'change' => $quote['09. change'] ?? null,
                            'change_percent' => $quote['10. change percent'] ?? null,
                        ];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("Failed to fetch quote for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get historical data for a stock
     */
    public function getHistoricalData(string $symbol, string $outputSize = 'compact'): ?array
    {
        $cacheKey = "stock_history_{$symbol}_{$outputSize}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $outputSize) {
            try {
                $response = Http::get($this->baseUrl, [
                    'function' => 'TIME_SERIES_DAILY',
                    'symbol' => $symbol,
                    'outputsize' => $outputSize,
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['Time Series (Daily)'])) {
                        return $data['Time Series (Daily)'];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("Failed to fetch history for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Search for stocks by keyword
     */
    public function searchStocks(string $keywords): ?array
    {
        try {
            $response = Http::get($this->baseUrl, [
                'function' => 'SYMBOL_SEARCH',
                'keywords' => $keywords,
                'apikey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                if (isset($data['bestMatches'])) {
                    return $data['bestMatches'];
                }
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("Failed to search stocks: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get company overview
     */
    public function getCompanyOverview(string $symbol): ?array
    {
        $cacheKey = "company_overview_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol) {
            try {
                $response = Http::get($this->baseUrl, [
                    'function' => 'OVERVIEW',
                    'symbol' => $symbol,
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("Failed to fetch overview for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }
}
