<?php

return [
    'leaderboard' => env('CACHE_TTL_LEADERBOARD', 300), // seconds
    'stock_quote' => env('CACHE_TTL_STOCK_QUOTE', 300),
    'stock_profile' => env('CACHE_TTL_STOCK_PROFILE', 604800), // 7 days
    'tradable_stocks' => env('CACHE_TTL_TRADABLE', 2592000), // 30 days
];
