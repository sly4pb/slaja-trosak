<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_price_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracked_product_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 15, 2);
            $table->timestamp('checked_at');
            $table->timestamps();

            $table->index(['tracked_product_id', 'checked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_price_history');
    }
};
