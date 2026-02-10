<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Clean or Archive Old Audit Records
 * 
 * PURPOSE:
 * Prevent unbounded growth of the portfolio_audit table while maintaining historical data
 * for operational debugging and compliance investigation.
 * 
 * RETENTION POLICY (by design):
 * - Default: 730 days (2 years) of audit records retained
 * - Rationale: Balances compliance investigation needs with storage cost
 * - Configurable: Use --days option to override for different policies
 * 
 * COMPLIANCE NOTE:
 * - Do NOT run this command if regulatory requirements mandate longer retention
 * - For regulated use cases, partition audit table by month and ARCHIVE to S3 instead of DELETE
 * - See: PRODUCTION_SCALE_FIXES_GUIDE.md "Next prioritized technical tasks"
 * 
 * OPERATIONAL CONSIDERATIONS:
 * - This command is SAFE to run; it only deletes old historical data
 * - It is NOT automatically scheduled; must be configured in production scheduler
 * - For large audit tables (millions of rows), consider running during off-peak hours
 * - Monitor table size before/after with: SELECT COUNT(*) FROM portfolio_audit
 * 
 * USAGE:
 *   php artisan audit:clean                    # Delete records >730 days old
 *   php artisan audit:clean --days=1825        # Delete records >5 years old (5*365)
 *   php artisan audit:clean --days=30          # Delete records >30 days old (caution!)
 */
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
