<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanOldAudits extends Command
{
    protected $signature = 'audit:clean {--days=730}';
    protected $description = 'Delete or archive portfolio audit records older than given days (default: 730)';

    public function handle()
    {
        $days = (int) $this->option('days');
        $cutoff = now()->subDays($days);

        $deleted = DB::table('portfolio_audit')
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Deleted {$deleted} audit records older than {$days} days");

        return 0;
    }
}
