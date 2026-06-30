<?php

namespace App\Models;

use App\Enums\BankType;
use App\Enums\TransactionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'bank_upload_id',
        'bank',
        'date',
        'transaction_date',
        'type',
        'category',
        'description',
        'amount',
        'currency',
        'raw',
    ];

    protected $casts = [
        'bank'             => BankType::class,
        'category'         => TransactionCategory::class,
        'date'             => 'date',
        'transaction_date' => 'date',
        'amount'           => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bankUpload(): BelongsTo
    {
        return $this->belongsTo(BankUpload::class);
    }

    public function isExpense(): bool
    {
        return $this->amount < 0;
    }

    public function isIncome(): bool
    {
        return $this->amount > 0;
    }
}
