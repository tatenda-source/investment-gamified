<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StockApiService
{
    private const BASE_URL = 'https://www.alphavantage.co/query';

    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = (string) config('services.alphavantage.key');
    }

    public function getQuote(string $symbol): ?array
    {
        $cacheKey = "stock_quote_{$symbol}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($symbol): ?array {
            $data = $this->fetchData([
                'function' => 'GLOBAL_QUOTE',
                'symbol' => $symbol,
                'apikey' => $this->apiKey,
            ], 5, "Failed to fetch quote for {$symbol}");

            if (!isset($data['Global Quote'])) {
                return null;
            }

            $quote = $data['Global Quote'];

            return [
                'symbol' => $quote['01. symbol'] ?? null,
                'price' => $quote['05. price'] ?? null,
                'change' => $quote['09. change'] ?? null,
                'change_percent' => $quote['10. change percent'] ?? null,
            ];
        });
    }

    public function getHistoricalData(string $symbol, string $outputSize = 'compact'): ?array
    {
        $cacheKey = "stock_history_{$symbol}_{$outputSize}";

        return Cache::remember($cacheKey, now()->addHours(24), function () use ($symbol, $outputSize): ?array {
            $data = $this->fetchData([
                'function' => 'TIME_SERIES_DAILY',
                'symbol' => $symbol,
                'outputsize' => $outputSize,
                'apikey' => $this->apiKey,
            ], 10, "Failed to fetch history for {$symbol}");

            return $data['Time Series (Daily)'] ?? null;
        });
    }

    public function searchStocks(string $keywords): ?array
    {
        $data = $this->fetchData([
            'function' => 'SYMBOL_SEARCH',
            'keywords' => $keywords,
            'apikey' => $this->apiKey,
        ], 5, 'Failed to search stocks');

        return $data['bestMatches'] ?? null;
    }

    public function getCompanyOverview(string $symbol): ?array
    {
        $cacheKey = "company_overview_{$symbol}";

        return Cache::remember($cacheKey, now()->addDays(7), function () use ($symbol): ?array {
            return $this->fetchData([
                'function' => 'OVERVIEW',
                'symbol' => $symbol,
                'apikey' => $this->apiKey,
            ], 5, "Failed to fetch overview for {$symbol}");
        });
    }

    private function fetchData(array $query, int $timeoutSeconds, string $errorMessage): ?array
    {
        try {
            $response = Http::timeout($timeoutSeconds)
                ->retry(3, 100)
                ->get(self::BASE_URL, $query);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error($errorMessage . ': ' . $e->getMessage());

            return null;
        }
    }
}
