<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->uuid('salon_id')->nullable()->change();
            $table->foreign('salon_id')->references('id')->on('salons')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['salon_id']);
            $table->uuid('salon_id')->nullable(false)->change();
            $table->foreign('salon_id')->references('id')->on('salons')->cascadeOnDelete();
        });
    }
};
