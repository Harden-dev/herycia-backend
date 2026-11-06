<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pentest GeniusPay :
 * - M1 : refund_check_pending_at marque une transaction dont un webhook payment.refunded n'a pas pu
 *   être vérifié (API indisponible) ; la réconciliation la relit jusqu'à vérification ;
 * - M2 : état de l'abonnement avant le paiement (plan, fin de période, statut), pour restaurer
 *   la période de l'ancien plan si le paiement est remboursé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->timestamp('refund_check_pending_at')->nullable()->index();
        });

        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->foreignUuid('previous_plan_id')->nullable()->constrained('plans')->nullOnDelete();
            $table->date('previous_ends_at')->nullable();
            $table->string('previous_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('previous_plan_id');
            $table->dropColumn(['previous_ends_at', 'previous_status']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropIndex(['refund_check_pending_at']);
            $table->dropColumn('refund_check_pending_at');
        });
    }
};
