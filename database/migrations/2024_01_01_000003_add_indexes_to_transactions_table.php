<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Filter po tipu transakcije (Kupovina, Bankomat, Uplata...)
            $table->index(['user_id', 'type'], 'transactions_user_type_idx');

            // Filter po uploadu (vec ima FK, ali dodajemo composite za brze filtriranje po useru)
            $table->index(['user_id', 'bank_upload_id'], 'transactions_user_upload_idx');

            // Date-range filter (vec postoji ['user_id','date'], ali dodajemo cisti date index
            // za slucaj sortiranja/filtriranja bez user_id u nekim upitima)
            $table->index('date', 'transactions_date_idx');

            // Amount — koristi se za rashodi/prihodi filtere (amount < 0 / > 0)
            $table->index(['user_id', 'amount'], 'transactions_user_amount_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_type_idx');
            $table->dropIndex('transactions_user_upload_idx');
            $table->dropIndex('transactions_date_idx');
            $table->dropIndex('transactions_user_amount_idx');
        });
    }
};
