<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stock extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'name',
        'description',
        'kid_friendly_description',
        'fun_fact',
        'category',
        'current_price',
        'change_percentage',
        'logo_url',
    ];

    protected $casts = [
        'current_price' => 'decimal:2',
        'change_percentage' => 'decimal:2',
    ];

    public function portfolios(): HasMany
    {
        return $this->hasMany(Portfolio::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(StockHistory::class);
    }
}
