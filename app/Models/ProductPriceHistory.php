<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPriceHistory extends Model
{
    protected $table = 'product_price_history';

    protected $fillable = [
        'tracked_product_id',
        'price',
        'checked_at',
    ];

    protected $casts = [
        'price'      => 'decimal:2',
        'checked_at' => 'datetime',
    ];

    public function trackedProduct(): BelongsTo
    {
        return $this->belongsTo(TrackedProduct::class);
    }
}
