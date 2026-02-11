<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Clean old audits daily at 02:00
        $schedule->command('audit:clean')->daily()->at('02:00');
    }

    protected function commands()
    {
        $this->load(__DIR__ . '/Commands');
    }
}
