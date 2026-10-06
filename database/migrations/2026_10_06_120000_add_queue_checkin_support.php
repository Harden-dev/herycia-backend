<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File d'attente V1 : enregistrement à l'arrivée, gestion du retard et suivi public.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            // Clé du QR d'arrivée affiché au salon (preuve de présence), régénérable par l'admin.
            $table->string('checkin_key', 64)->nullable();
            // Retard toléré avant que le créneau soit perdu.
            $table->unsignedSmallInteger('late_tolerance_minutes')->default(15);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->timestamp('checked_in_at')->nullable();
            // Heure initiale quand le client en retard a choisi « même heure, un autre jour ».
            $table->dateTime('rescheduled_from')->nullable();
        });

        Schema::table('queue_entries', function (Blueprint $table) {
            $table->foreignUuid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            // appointment : à l'heure, ancré sur l'heure du RDV ; late : retardataire placé après le dernier.
            $table->string('source', 20)->default('appointment');
            // Ordre de passage : heure du RDV (ancré) ou heure d'arrivée (retardataire).
            $table->dateTime('priority_at')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->string('tracking_token', 40)->nullable()->unique();

            $table->index(['salon_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropIndex(['salon_id', 'user_id', 'status']);
            $table->dropConstrainedForeignId('appointment_id');
            $table->dropUnique(['tracking_token']);
            $table->dropColumn(['source', 'priority_at', 'started_at', 'tracking_token']);
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropColumn(['checked_in_at', 'rescheduled_from']);
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn(['checkin_key', 'late_tolerance_minutes']);
        });
    }
};
