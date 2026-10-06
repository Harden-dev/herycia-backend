<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit de sécurité :
 * - users.password_changed_at : révoque les JWT émis avant un changement de mot de passe (H2)
 * - salons.deleted_at : suppression logique, conserve les données financières (M4)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_changed_at')->nullable();
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_changed_at');
        });

        Schema::table('salons', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
