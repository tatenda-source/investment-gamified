<?php
// database/seeders/StockSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;
use App\Services\FinancialModelingPrepService;

class StockSeeder extends Seeder
{
    public function run(FinancialModelingPrepService $fmp)
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
            $quote = $fmp->getQuote($symbol);
            $profile = $fmp->getCompanyProfile($symbol);
            
            if ($quote && $profile) {
                Stock::updateOrCreate(
                    ['symbol' => $symbol],
                    [
                        'name' => $quote['name'] ?? $profile['companyName'],
                        'description' => $profile['description'] ?? '',
                        'kid_friendly_description' => $kidFriendly,
                        'category' => $this->categorizeStock($profile),
                        'current_price' => $quote['price'],
                        'change_percentage' => $quote['change_percent'] ?? 0,
                        'logo_url' => $profile['image'] ?? null,
                    ]
                );
                
                echo "Imported {$symbol}\n";
            }
            
            // Rate limiting
            sleep(1);
        }
    }

    private function categorizeStock(array $profile): string
    {
        $sector = strtolower($profile['sector'] ?? '');
        
        return match(true) {
            str_contains($sector, 'tech') => 'Tech',
            str_contains($sector, 'consumer') => 'Retail',
            str_contains($sector, 'food') => 'Food',
            str_contains($sector, 'entertainment') => 'Entertainment',
            str_contains($sector, 'financial') => 'Finance',
            default => 'Other',
        };
    }
}
