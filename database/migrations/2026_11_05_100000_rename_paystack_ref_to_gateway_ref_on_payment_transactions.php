<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passage Paystack → GeniusPay : la référence du prestataire (MTX-… chez GeniusPay)
 * est désormais distincte de notre référence interne (SAL-…) et sert à retrouver
 * la transaction depuis le webhook, d'où l'index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->renameColumn('paystack_ref', 'gateway_ref');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->index('gateway_ref');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['gateway_ref']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->renameColumn('gateway_ref', 'paystack_ref');
        });
    }
};
