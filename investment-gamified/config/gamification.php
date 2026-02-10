<?php

return [
    'xp' => [
        'buy_reward' => env('XP_BUY_REWARD', 10),
        'sell_reward' => env('XP_SELL_REWARD', 15),
    ],

    'level_up' => [
        'base_xp' => env('LEVEL_BASE_XP', 1000),
        // For simplicity initial implementation uses linear base XP per level.
        // More complex formulas can be implemented later.
    ],
];
