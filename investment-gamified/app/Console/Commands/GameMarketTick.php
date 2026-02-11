<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;

class GameMarketTick extends Command
{
    protected $signature = 'game:market-tick';
    protected $description = 'Simulate market movement by updating stock prices every few seconds';

    public function handle(): int
    {
        $this->info('Starting market simulation... Press Ctrl+C to stop.');

        while (true) {
            $stocks = Stock::all();

            foreach ($stocks as $stock) {
                $percentChange = mt_rand(-50, 50) / 10000;
                $changeAmount = $stock->current_price * $percentChange;
                $newPrice = $stock->current_price + $changeAmount;

                if ($newPrice < 0.01) {
                    $newPrice = 0.01;
                }

                $stock->current_price = $newPrice;
                $stock->change_percentage += ($percentChange * 100);
                $stock->save();

                $this->output->write('.');
            }

            sleep(mt_rand(3, 5));
        }

        return self::SUCCESS;
    }
}
