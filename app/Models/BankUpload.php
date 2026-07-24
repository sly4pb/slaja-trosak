<?php

namespace App\Models;

use App\Enums\BankType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankUpload extends Model
{
    protected $fillable = [
        'user_id',
        'bank',
        'original_filename',
        'stored_path',
        'status',
        'transactions_count',
        'error_message',
    ];

    protected $casts = [
        'bank' => BankType::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }
}
