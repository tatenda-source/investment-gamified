<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use App\Services\FinancialModelingPrepService;
use Illuminate\Support\Facades\Log;

class SeedStocksFromFmp extends Command
{
    protected $signature = 'stocks:seed-from-fmp {--limit=200 : Maximum number of stocks to seed} {--force : Continue despite errors}';
    protected $description = 'Fetch tradable stocks from FinancialModelingPrep and seed the stocks table using updateOrCreate';

    public function handle(FinancialModelingPrepService $fmp)
    {
        $limit = (int) $this->option('limit');
        $force = (bool) $this->option('force');

        $this->info("Fetching tradable stocks from FMP (limit={$limit})...");

        try {
            $all = $fmp->getTradableStocks();

            if (empty($all) || !is_array($all)) {
                $this->error('No stocks returned from FMP or invalid response.');
                return 1;
            }

            $count = 0;

            foreach ($all as $item) {
                if ($count >= $limit) {
                    break;
                }

                $symbol = strtoupper($item['symbol'] ?? ($item['ticker'] ?? null));
                $name = $item['name'] ?? ($item['companyName'] ?? null);

                if (!$symbol || !$name) {
                    if (!$force) continue;
                }

                $price = 0.0;
                try {
                    $quote = $fmp->getQuote($symbol);
                    $price = $quote['price'] ?? 0.0;
                } catch (\Exception $e) {
                    Log::warning("Quote fetch failed for {$symbol}: {$e->getMessage()}");
                    if (!$force) continue;
                }

                Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'name' => $name,
                        'current_price' => $price,
                        'description' => $item['description'] ?? null,
                        'kid_friendly_description' => $item['description'] ?? null,
                        'category' => $item['sector'] ?? null,
                        'change_percentage' => $quote['change_percent'] ?? 0,
                        'logo_url' => $item['logo'] ?? $item['image'] ?? null,
                        'available' => 1000,
                    ]
                );

                $count++;
                // Gentle pace to avoid rate-limits
                usleep(200000);
            }

            $this->info("Seed complete: {$count} stocks added/updated.");
            return 0;
        } catch (\Exception $e) {
            Log::error('SeedStocksFromFmp failed: ' . $e->getMessage());
            $this->error('Operation failed: ' . $e->getMessage());
            return 1;
        }
    }
}
