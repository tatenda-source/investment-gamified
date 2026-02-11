<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ApiQuotaTracker
{
    public function recordRequest(string $service, int $count = 1)
    {
        $today = date('Y-m-d');
        $key = "api_quota_{$service}_{$today}";
        $current = Cache::get($key, 0);
        Cache::put($key, $current + $count, now()->endOfDay());

        $limit = config("services.{$service}.daily_limit", null);
        if ($limit && $current + $count >= $limit * 0.8) {
            Log::warning("API quota 80% used for {$service}");
        }
    }

    public function hasQuota(string $service): bool
    {
        $today = date('Y-m-d');
        $key = "api_quota_{$service}_{$today}";
        $used = Cache::get($key, 0);
        $limit = config("services.{$service}.daily_limit", null);

        if ($limit === null) {
            return true;
        }

        return $used < $limit;
    }
}
