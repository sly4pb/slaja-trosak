<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Kljucna rec iz opisa transakcije (npr. "MP587 S. MARKOVICA")
            $table->string('keyword');
            $table->string('category'); // TransactionCategory enum value
            $table->timestamps();

            // Jedan user ne moze imati dva pravila za isti keyword
            $table->unique(['user_id', 'keyword']);
            $table->index(['user_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_rules');
    }
};
