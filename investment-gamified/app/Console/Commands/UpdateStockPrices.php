<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Stock;
use App\Services\StockApiService;
use Illuminate\Console\Command;

class UpdateStockPrices extends Command
{
    protected $signature = 'stocks:update-prices';
    protected $description = 'Update stock prices from Alpha Vantage API';

    public function handle(StockApiService $stockApi): int
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
            sleep(12);
        }

        $bar->finish();
        $this->newLine();
        $this->info('Stock prices updated successfully!');

        return self::SUCCESS;
    }
}
