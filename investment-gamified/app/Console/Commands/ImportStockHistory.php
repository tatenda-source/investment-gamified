<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Stock;
use App\Models\StockHistory;
use App\Services\StockApiService;
use Illuminate\Console\Command;

class ImportStockHistory extends Command
{
    protected $signature = 'stocks:import-history {symbol?}';
    protected $description = 'Import historical stock data from Alpha Vantage API';

    public function handle(StockApiService $stockApi): int
    {
        $symbol = $this->argument('symbol');
        $stocks = $symbol ? Stock::where('symbol', $symbol)->get() : Stock::all();

        foreach ($stocks as $stock) {
            $this->info("Importing history for {$stock->symbol}...");

            $history = $stockApi->getHistoricalData($stock->symbol);

            if ($history) {
                foreach ($history as $date => $data) {
                    StockHistory::updateOrCreate(
                        [
                            'stock_id' => $stock->id,
                            'date' => $date,
                        ],
                        [
                            'open_price' => $data['1. open'],
                            'high_price' => $data['2. high'],
                            'low_price' => $data['3. low'],
                            'close_price' => $data['4. close'],
                        ],
                    );
                }

                $this->info('Imported ' . count($history) . ' days of history');
            }

            sleep(12);
        }

        $this->info('History import completed!');

        return self::SUCCESS;
    }
}
