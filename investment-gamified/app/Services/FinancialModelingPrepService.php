<?php
// app/Services/FinancialModelingPrepService.php
namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Financial Modeling Prep API Service
 * Free tier: 250 requests per day
 * Get API key at: https://site.financialmodelingprep.com/developer/docs/
 */
class FinancialModelingPrepService
{
    protected string $apiKey;
    protected string $baseUrl = 'https://financialmodelingprep.com/api/v3';

    public function __construct()
    {
        $this->apiKey = config('services.fmp.key');
    }

    /**
     * Get real-time stock quote
     */
    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "fmp_quote_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol) {
            try {
                $response = Http::get("{$this->baseUrl}/quote/{$symbol}", [
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (!empty($data) && isset($data[0])) {
                        $quote = $data[0];
                        return [
                            'symbol' => $quote['symbol'] ?? null,
                            'name' => $quote['name'] ?? null,
                            'price' => $quote['price'] ?? null,
                            'change' => $quote['change'] ?? null,
                            'change_percent' => $quote['changesPercentage'] ?? null,
                            'volume' => $quote['volume'] ?? null,
                            'market_cap' => $quote['marketCap'] ?? null,
                        ];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("FMP: Failed to fetch quote for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get company profile (includes kid-friendly info)
     */
    public function getCompanyProfile(string $symbol): ?array
    {
        $cacheKey = "fmp_profile_{$symbol}";
        
        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol) {
            try {
                $response = Http::get("{$this->baseUrl}/profile/{$symbol}", [
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (!empty($data) && isset($data[0])) {
                        return $data[0];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("FMP: Failed to fetch profile for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Get historical prices
     */
    public function getHistoricalPrices(string $symbol, int $days = 30): ?array
    {
        $cacheKey = "fmp_history_{$symbol}_{$days}";
        
        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $days) {
            try {
                $from = now()->subDays($days)->format('Y-m-d');
                $to = now()->format('Y-m-d');
                
                $response = Http::get("{$this->baseUrl}/historical-price-full/{$symbol}", [
                    'from' => $from,
                    'to' => $to,
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    
                    if (isset($data['historical'])) {
                        return $data['historical'];
                    }
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("FMP: Failed to fetch history for {$symbol}: " . $e->getMessage());
                return null;
            }
        });
    }

    /**
     * Search for stocks
     */
    public function searchStocks(string $query): ?array
    {
        try {
            $response = Http::get("{$this->baseUrl}/search", [
                'query' => $query,
                'limit' => 10,
                'apikey' => $this->apiKey,
            ]);

            if ($response->successful()) {
                return $response->json();
            }
            
            return null;
        } catch (\Exception $e) {
            Log::error("FMP: Failed to search stocks: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get list of tradable stocks (good for seeding database)
     */
    public function getTradableStocks(): ?array
    {
        $cacheKey = "fmp_tradable_stocks";
        
        return Cache::remember($cacheKey, now()->addDays(30), function () {
            try {
                $response = Http::get("{$this->baseUrl}/stock/list", [
                    'apikey' => $this->apiKey,
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
                
                return null;
            } catch (\Exception $e) {
                Log::error("FMP: Failed to fetch tradable stocks: " . $e->getMessage());
                return null;
            }
        });
    }
}
