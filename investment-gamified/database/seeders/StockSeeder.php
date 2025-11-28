<?php
// database/seeders/StockSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;
use App\Services\StockApiService;

class StockSeeder extends Seeder
{
    public function run(StockApiService $stockService)
    {
        // Popular kid-friendly stocks
        $popularSymbols = [
            'AAPL' => 'Apple makes iPhones and iPads',
            'MSFT' => 'Microsoft makes Xbox and Windows',
            'DIS' => 'Disney owns Mickey Mouse and Marvel',
            'GOOGL' => 'Google helps you search the internet',
            'AMZN' => 'Amazon delivers packages to your door',
            'TSLA' => 'Tesla makes electric cars',
            'NKE' => 'Nike makes cool sneakers',
            'MCD' => 'McDonald\'s serves burgers and fries',
            'SBUX' => 'Starbucks makes coffee drinks',
            'NFLX' => 'Netflix streams movies and shows',
        ];

        foreach ($popularSymbols as $symbol => $kidFriendly) {
            $quote = $stockService->getQuote($symbol);
            $overview = $stockService->getCompanyOverview($symbol);
            
            if ($quote) {
                Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'name' => $overview['Name'] ?? $symbol,
                        'description' => $overview['Description'] ?? '',
                        'kid_friendly_description' => $kidFriendly,
                        'category' => $overview['Sector'] ?? 'Other',
                        'current_price' => $quote['price'],
                        'change_percentage' => rtrim($quote['change_percent'] ?? '0%', '%'),
                        'logo_url' => null, // AlphaVantage doesn't provide logos
                        'fun_fact' => $overview['Industry'] ?? 'A cool company!', // Fallback for fun_fact
                    ]
                );
                
                echo "Imported {$symbol}\n";
            } else {
                echo "Failed to fetch {$symbol}\n";
            }
            
            // Rate limiting (AlphaVantage free tier is 5 calls/minute usually, but let's be safe)
            sleep(15); 
        }
    }

    private function categorizeStock(array $profile): string
    {
        // Not used anymore but keeping for reference if needed
        return 'Other';
    }
}
