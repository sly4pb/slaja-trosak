<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackedProduct extends Model
{
    protected $fillable = [
        'user_id',
        'url',
        'product_name',
        'current_price',
        'currency',
        'status',
        'error_message',
        'last_checked_at',
    ];

    protected $casts = [
        'current_price'    => 'decimal:2',
        'last_checked_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(ProductPriceHistory::class)->orderByDesc('checked_at');
    }

    public function isOk(): bool
    {
        return $this->status === 'ok';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
