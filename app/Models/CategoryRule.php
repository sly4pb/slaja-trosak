<?php

namespace App\Models;

use App\Enums\TransactionCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    protected $fillable = [
        'user_id',
        'keyword',
        'category',
    ];

    protected $casts = [
        'category' => TransactionCategory::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Provjeri da li dati opis transakcije matchuje ovaj keyword (case-insensitive).
     */
    public function matches(string $description): bool
    {
        return str_contains(
            mb_strtolower($description),
            mb_strtolower($this->keyword)
        );
    }
}
