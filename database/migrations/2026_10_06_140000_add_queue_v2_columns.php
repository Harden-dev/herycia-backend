<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * File d'attente V2 : SMS « c'est bientôt votre tour » envoyé une seule fois par passage.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->timestamp('soon_notified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('queue_entries', function (Blueprint $table) {
            $table->dropColumn('soon_notified_at');
        });
    }
};
