<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Contre-audit GeniusPay :
 * - N5 : nombre de passages de réconciliation sur un remboursement annoncé non confirmé ; au-delà
 *   du seuil (geniuspay.refund_check_max_attempts), la relecture est abandonnée avec une alerte ;
 * - M4 : montant brut réellement reçu, à côté du prix (amount) utilisé par les rapports.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedSmallInteger('refund_check_attempts')->default(0);
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->unsignedInteger('gateway_amount')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn('gateway_amount');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('refund_check_attempts');
        });
    }
};
