<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Portfolio extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'stock_id',
        'quantity',
        'average_price',
    ];

    protected $casts = [
        'average_price' => 'decimal:2',
    ];

    protected static function booted()
    {
        // Exclude zero-quantity entries by default at the model level so
        // API endpoints and queries do not return meaningless holdings.
        static::addGlobalScope('has_quantity', function ($builder) {
            $builder->where('quantity', '>', 0);
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
