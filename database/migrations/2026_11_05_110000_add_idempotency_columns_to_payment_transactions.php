<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GeniusPay n'offre pas de clé d'idempotence : la clé envoyée par le front est stockée
 * ici (unique par salon) avec le lien de paiement, pour rejouer la même réponse sans
 * recréer de paiement chez le prestataire.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 128)->nullable();
            $table->text('checkout_url')->nullable();
            $table->timestamp('checkout_expires_at')->nullable();

            $table->unique(['salon_id', 'idempotency_key']);
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropUnique(['salon_id', 'idempotency_key']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['idempotency_key', 'checkout_url', 'checkout_expires_at']);
        });
    }
};
