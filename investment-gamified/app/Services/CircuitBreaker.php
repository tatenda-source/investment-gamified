<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CircuitBreaker
{
    protected string $service;
    protected int $failureThreshold;
    protected int $openDurationSeconds;

    public function __construct(string $service, int $failureThreshold = 5, int $openDurationSeconds = 300)
    {
        $this->service = $service;
        $this->failureThreshold = $failureThreshold;
        $this->openDurationSeconds = $openDurationSeconds;
    }

    public function call(callable $function, callable $fallback = null)
    {
        $state = $this->getState();

        if ($state === 'open') {
            Log::warning("Circuit open for {$this->service}");
            if ($fallback) {
                return $fallback();
            }
            throw new \Exception("Circuit open for {$this->service}");
        }

        try {
            $result = $function();
            $this->recordSuccess();
            return $result;
        } catch (\Exception $e) {
            $this->recordFailure();
            Log::error("CircuitBreaker {$this->service} failure: " . $e->getMessage());
            if ($fallback) {
                return $fallback();
            }
            throw $e;
        }
    }

    protected function recordFailure()
    {
        $key = "cb_{$this->service}_failures";
        $failures = Cache::get($key, 0) + 1;
        Cache::put($key, $failures, 60);

        if ($failures >= $this->failureThreshold) {
            Cache::put("cb_{$this->service}_state", 'open', $this->openDurationSeconds);
            Log::warning("Circuit for {$this->service} moved to OPEN state");
        }
    }

    protected function recordSuccess()
    {
        Cache::forget("cb_{$this->service}_failures");
        Cache::forget("cb_{$this->service}_state");
    }

    protected function getState(): string
    {
        return Cache::get("cb_{$this->service}_state", 'closed');
    }
}
