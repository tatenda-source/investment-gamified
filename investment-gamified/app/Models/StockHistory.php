<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'stock_id',
        'date',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
    ];

    protected $casts = [
        'date' => 'date',
        'open_price' => 'decimal:2',
        'high_price' => 'decimal:2',
        'low_price' => 'decimal:2',
        'close_price' => 'decimal:2',
    ];

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }
}
