<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PortfolioAudit extends Model
{
    // Table name is singular to match the audit table naming in migrations
    protected $table = 'portfolio_audit';

    protected $guarded = [];

    public $timestamps = true; // uses created_at

    // Immutable ledger: prevent accidental updates/deletes at application level.
    public function update(array $attributes = [], array $options = [])
    {
        throw new \Exception('PortfolioAudit is immutable and cannot be updated');
    }

    public function delete()
    {
        throw new \Exception('PortfolioAudit is immutable and cannot be deleted');
    }

    public static function query()
    {
        $query = parent::query();
        // Block mass updates through query builder on this model
        $query->macro('update', function () {
            throw new \Exception('Mass updates are not allowed on PortfolioAudit');
        });
        return $query;
    }
}
