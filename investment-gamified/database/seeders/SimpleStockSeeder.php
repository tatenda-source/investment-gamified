<?php
// database/seeders/SimpleStockSeeder.php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Stock;

class SimpleStockSeeder extends Seeder
{
    public function run()
    {
        $stocks = [
            [
                'symbol' => 'AAPL',
                'name' => 'Apple Inc.',
                'description' => 'Apple Inc. designs, manufactures, and markets smartphones, personal computers, tablets, wearables, and accessories worldwide.',
                'kid_friendly_description' => 'Apple makes iPhones, iPads, and Mac computers that people use every day!',
                'fun_fact' => 'The first iPhone was released in 2007 and changed how we use phones!',
                'category' => 'Tech',
                'current_price' => 189.95,
                'change_percentage' => 1.25,
                'logo_url' => null,
            ],
            [
                'symbol' => 'MSFT',
                'name' => 'Microsoft Corporation',
                'description' => 'Microsoft Corporation develops, licenses, and supports software, services, devices, and solutions worldwide.',
                'kid_friendly_description' => 'Microsoft makes Xbox gaming consoles and Windows for computers!',
                'fun_fact' => 'Microsoft was founded by Bill Gates and Paul Allen in 1975!',
                'category' => 'Tech',
                'current_price' => 378.50,
                'change_percentage' => 0.85,
                'logo_url' => null,
            ],
            [
                'symbol' => 'DIS',
                'name' => 'The Walt Disney Company',
                'description' => 'The Walt Disney Company operates as an entertainment company worldwide.',
                'kid_friendly_description' => 'Disney owns Mickey Mouse, Marvel superheroes, and Star Wars!',
                'fun_fact' => 'Mickey Mouse was created in 1928 and is one of the most famous characters ever!',
                'category' => 'Entertainment',
                'current_price' => 93.25,
                'change_percentage' => -0.45,
                'logo_url' => null,
            ],
            [
                'symbol' => 'GOOGL',
                'name' => 'Alphabet Inc.',
                'description' => 'Alphabet Inc. provides various products and platforms in the United States, Europe, the Middle East, Africa, the Asia-Pacific, Canada, and Latin America.',
                'kid_friendly_description' => 'Google helps you search for anything on the internet!',
                'fun_fact' => 'Google processes over 8.5 billion searches per day!',
                'category' => 'Tech',
                'current_price' => 141.80,
                'change_percentage' => 2.10,
                'logo_url' => null,
            ],
            [
                'symbol' => 'AMZN',
                'name' => 'Amazon.com Inc.',
                'description' => 'Amazon.com, Inc. engages in the retail sale of consumer products and subscriptions in North America and internationally.',
                'kid_friendly_description' => 'Amazon delivers packages to your door and streams movies!',
                'fun_fact' => 'Amazon started as an online bookstore in 1994!',
                'category' => 'Retail',
                'current_price' => 178.25,
                'change_percentage' => 1.55,
                'logo_url' => null,
            ],
            [
                'symbol' => 'TSLA',
                'name' => 'Tesla Inc.',
                'description' => 'Tesla, Inc. designs, develops, manufactures, leases, and sells electric vehicles, and energy generation and storage systems.',
                'kid_friendly_description' => 'Tesla makes cool electric cars that don\'t need gas!',
                'fun_fact' => 'Tesla cars can drive themselves with Autopilot technology!',
                'category' => 'Tech',
                'current_price' => 242.80,
                'change_percentage' => 3.25,
                'logo_url' => null,
            ],
            [
                'symbol' => 'NKE',
                'name' => 'Nike Inc.',
                'description' => 'NIKE, Inc., together with its subsidiaries, designs, develops, markets, and sells athletic footwear, apparel, equipment, and accessories worldwide.',
                'kid_friendly_description' => 'Nike makes awesome sneakers and sports clothes!',
                'fun_fact' => 'The Nike "Swoosh" logo was designed for only $35!',
                'category' => 'Retail',
                'current_price' => 75.40,
                'change_percentage' => -0.30,
                'logo_url' => null,
            ],
            [
                'symbol' => 'MCD',
                'name' => 'McDonald\'s Corporation',
                'description' => 'McDonald\'s Corporation operates and franchises McDonald\'s restaurants in the United States and internationally.',
                'kid_friendly_description' => 'McDonald\'s serves burgers, fries, and Happy Meals!',
                'fun_fact' => 'McDonald\'s serves 69 million customers every day!',
                'category' => 'Food',
                'current_price' => 295.60,
                'change_percentage' => 0.65,
                'logo_url' => null,
            ],
            [
                'symbol' => 'SBUX',
                'name' => 'Starbucks Corporation',
                'description' => 'Starbucks Corporation, together with its subsidiaries, operates as a roaster, marketer, and retailer of specialty coffee worldwide.',
                'kid_friendly_description' => 'Starbucks makes coffee drinks and yummy treats!',
                'fun_fact' => 'Starbucks serves over 100 million customers per week!',
                'category' => 'Food',
                'current_price' => 98.75,
                'change_percentage' => 1.10,
                'logo_url' => null,
            ],
            [
                'symbol' => 'NFLX',
                'name' => 'Netflix Inc.',
                'description' => 'Netflix, Inc. provides entertainment services.',
                'kid_friendly_description' => 'Netflix streams movies and TV shows you can watch anytime!',
                'fun_fact' => 'Netflix started by mailing DVDs to people\'s homes!',
                'category' => 'Entertainment',
                'current_price' => 685.30,
                'change_percentage' => 2.45,
                'logo_url' => null,
            ],
            [
                'symbol' => 'META',
                'name' => 'Meta Platforms Inc.',
                'description' => 'Meta Platforms, Inc. engages in the development of products that enable people to connect and share with friends and family through mobile devices, personal computers, virtual reality headsets, and wearables worldwide.',
                'kid_friendly_description' => 'Meta owns Facebook, Instagram, and WhatsApp!',
                'fun_fact' => 'Over 3 billion people use Meta\'s apps every month!',
                'category' => 'Tech',
                'current_price' => 512.45,
                'change_percentage' => 1.85,
                'logo_url' => null,
            ],
            [
                'symbol' => 'KO',
                'name' => 'The Coca-Cola Company',
                'description' => 'The Coca-Cola Company, a beverage company, manufactures, markets, and sells various nonalcoholic beverages worldwide.',
                'kid_friendly_description' => 'Coca-Cola makes the famous Coke soda and many other drinks!',
                'fun_fact' => 'Coca-Cola was invented in 1886 by a pharmacist!',
                'category' => 'Food',
                'current_price' => 63.20,
                'change_percentage' => 0.45,
                'logo_url' => null,
            ],
        ];

        foreach ($stocks as $stockData) {
            Stock::updateOrCreate(
                ['symbol' => $stockData['symbol']],
                $stockData
            );
            echo "Created/Updated {$stockData['symbol']}\n";
        }

        echo "\nSuccessfully seeded " . count($stocks) . " stocks!\n";
    }
}
