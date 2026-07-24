<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Kategorija (House bills, Food, Pharmacy...) — null dok user ne dodeli
            $table->string('category')->nullable()->after('type');

            // Datum transakcije iz Excel fajla (npr. datum kupovine), odvojeno od created_at
            // koje ostaje sistemski timestamp (kad je red ubacen u bazu)
            $table->date('transaction_date')->nullable()->after('date');

            $table->index(['user_id', 'category'], 'transactions_user_category_idx');
            $table->index('transaction_date', 'transactions_transaction_date_idx');
        });

        // Popuni transaction_date postojecim vrednostima iz 'date' kolone
        // (date je vec datum iz Excela, samo ga kopiramo u novu kolonu)
        \DB::statement('UPDATE transactions SET transaction_date = date WHERE transaction_date IS NULL');
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_user_category_idx');
            $table->dropIndex('transactions_transaction_date_idx');
            $table->dropColumn(['category', 'transaction_date']);
        });
    }
};
