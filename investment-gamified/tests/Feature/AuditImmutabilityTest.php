<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\PortfolioAudit;

class AuditImmutabilityTest extends TestCase
{
    public function test_portfolio_audit_is_immutable()
    {
        $audit = PortfolioAudit::factory()->create();

        $this->expectException(\Exception::class);
        $audit->update(['quantity' => 999]);

        $this->expectException(\Exception::class);
        $audit->delete();
    }
}
