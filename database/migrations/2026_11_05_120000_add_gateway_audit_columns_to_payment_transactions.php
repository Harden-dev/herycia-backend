<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GeniusPay — audit et réconciliation :
 * - gateway : prestataire de la transaction ; les lignes antérieures viennent de Paystack et sont
 *   exclues de la réconciliation et du webhook GeniusPay ;
 * - gateway_amount / gateway_fees / gateway_net_amount : montants relus via l'API, pour audit
 *   (frais prélevés côté marchand) ;
 * - review_reason : motif d'un passage en needs_review ;
 * - last_checked_at : tri de la réconciliation (les jamais revérifiées d'abord, sans affamement).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('gateway', 20)->default('geniuspay');
            $table->unsignedInteger('gateway_amount')->nullable();
            $table->unsignedInteger('gateway_fees')->nullable();
            $table->unsignedInteger('gateway_net_amount')->nullable();
            $table->string('review_reason', 64)->nullable();
            $table->timestamp('last_checked_at')->nullable();

            $table->index(['gateway', 'status', 'last_checked_at']);
        });

        DB::table('payment_transactions')->update(['gateway' => 'paystack']);
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['gateway', 'status', 'last_checked_at']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn([
                'gateway',
                'gateway_amount',
                'gateway_fees',
                'gateway_net_amount',
                'review_reason',
                'last_checked_at',
            ]);
        });
    }
};
