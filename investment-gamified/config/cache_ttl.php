<?php

/**
 * Cache TTL Configuration
 * 
 * All values are in SECONDS. These values define the staleness window for cached data.
 * 
 * STALENESS TRADEOFF:
 * - Lower TTL = fresher data but higher DB load
 * - Higher TTL = lower DB load but potentially stale data
 * 
 * Each cache key is invalidated on relevant user/data mutation:
 * - leaderboard: flushed when any user gains XP/level (buy/sell)
 * - stock_quote: stale for up to TTL; represents last-fetched external price
 * - stock_profile: weeks-old data OK; low change frequency
 * - tradable_stocks: very stable; month-old listings acceptable
 */
return [
    // Leaderboard cache (paginated rankings by level/XP)
    // Default 5 minutes; acceptable for eventual consistency
    'leaderboard' => env('CACHE_TTL_LEADERBOARD', 300),
    
    // Stock quote cache (current price and performance)
    // Default 5 minutes; external API refresh delay + internal staleness
    'stock_quote' => env('CACHE_TTL_STOCK_QUOTE', 300),
    
    // Stock profile cache (name, description, category, etc.)
    // Default 7 days; static data, rarely changes
    'stock_profile' => env('CACHE_TTL_STOCK_PROFILE', 604800),
    
    // Tradable stocks listing (available stocks for purchase)
    // Default 30 days; admin-driven; low change frequency
    'tradable_stocks' => env('CACHE_TTL_TRADABLE', 2592000),
];
