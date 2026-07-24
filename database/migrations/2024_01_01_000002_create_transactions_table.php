<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bank_upload_id')->constrained()->cascadeOnDelete();
            $table->string('bank');                    // BankType enum value (denormalizovano za brze filtriranje)
            $table->date('date');
            $table->string('type')->nullable();        // TIP TRANSAKCIJE
            $table->text('description')->nullable();   // OPIS
            $table->decimal('amount', 15, 2);          // negativno = rashod, pozitivno = prihod
            $table->string('currency', 3)->default('RSD');
            $table->text('raw')->nullable();           // originalna linija iz fajla
            $table->timestamps();

            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'bank']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
