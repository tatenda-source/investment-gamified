<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioAudit extends Model
{
    // Table name is singular to match the audit table naming in migrations
    protected $table = 'portfolio_audit';

    protected $guarded = [];

    public $timestamps = true; // uses created_at

    // Immutable ledger: we do not provide any mutators here. Also
    // migrations create DB-level triggers to prevent UPDATE/DELETE.
}
