<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioAudit extends Model
{
    protected $table = 'portfolio_audit';

    protected $guarded = [];

    public $timestamps = true;
}
