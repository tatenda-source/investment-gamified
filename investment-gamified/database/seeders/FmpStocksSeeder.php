<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use App\Models\Stock;
use App\Services\FinancialModelingPrepService;

class FmpStocksSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * This seeder fetches tradable stocks from Financial Modeling Prep and upserts them.
     * It is careful with rate limits and errors.
     */
    public function run(FinancialModelingPrepService $fmp)
    {
        $this->command->info('Fetching tradable stocks from FMP...');

        try {
            $all = $fmp->getTradableStocks();

            if (empty($all) || !is_array($all)) {
                $this->command->error('No stocks returned from FMP or invalid response.');
                return;
            }

            // Limit number of seeds to a reasonable default to avoid huge imports on local machines
            $limit = (int) ($this->command->option('limit') ?? 200);
            $this->command->info("Processing up to {$limit} stocks...");

            $count = 0;

            foreach ($all as $item) {
                if ($count >= $limit) {
                    break;
                }

                // FMP stock record may contain a symbol and name properties
                $symbol = strtoupper($item['symbol'] ?? ($item['ticker'] ?? null));
                $name = $item['name'] ?? ($item['companyName'] ?? null);

                if (!$symbol || !$name) {
                    continue; // skip incomplete records
                }

                // Try to get a current price from FMP quote endpoint
                $quote = $fmp->getQuote($symbol);
                $price = $quote['price'] ?? null;

                // Default safe price when none available
                $price = $price !== null ? floatval($price) : 0.0;

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

                // Be friendly with FMP free tier limits
                usleep(200000); // 0.2s between items
            }

            $this->command->info("Seed complete: {$count} stocks added/updated.");
        } catch (\Exception $e) {
            Log::error('Seed FMP stocks failed: ' . $e->getMessage());
            $this->command->error('Failed to fetch or seed stocks: ' . $e->getMessage());
        }
    }
}
