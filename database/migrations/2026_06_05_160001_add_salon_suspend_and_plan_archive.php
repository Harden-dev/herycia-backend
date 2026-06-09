<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable()->after('is_active');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('is_archived')->default(false)->after('has_multi_branch');
        });
    }

    public function down(): void
    {
        Schema::table('salons', function (Blueprint $table) {
            $table->dropColumn('suspended_at');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('is_archived');
        });
    }
};
