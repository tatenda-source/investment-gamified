<?php
// app/Console/Commands/UpdateStockPrices.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Stock;
use App\Services\StockApiService;

class UpdateStockPrices extends Command
{
    protected $signature = 'stocks:update-prices';
    protected $description = 'Update stock prices from Alpha Vantage API';

    public function handle(StockApiService $stockApi)
    {
        $stocks = Stock::all();
        $bar = $this->output->createProgressBar(count($stocks));

        $this->info('Updating stock prices...');
        $bar->start();

        foreach ($stocks as $stock) {
            $quote = $stockApi->getQuote($stock->symbol);
            
            if ($quote && isset($quote['price'])) {
                $oldPrice = $stock->current_price;
                $newPrice = (float) $quote['price'];
                
                $stock->current_price = $newPrice;
                $stock->change_percentage = (($newPrice - $oldPrice) / $oldPrice) * 100;
                $stock->save();
            }
            
            $bar->advance();
            
            // Rate limiting: Alpha Vantage free tier allows 5 calls per minute
            sleep(12); // 60 seconds / 5 calls = 12 seconds per call
        }

        $bar->finish();
        $this->newLine();
        $this->info('Stock prices updated successfully!');
    }
}
