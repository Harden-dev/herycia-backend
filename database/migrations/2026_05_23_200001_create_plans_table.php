<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedInteger('price_fcfa');
            $table->unsignedInteger('max_employees');
            $table->unsignedInteger('max_services');
            $table->boolean('has_online_booking')->default(false);
            $table->boolean('has_analytics')->default(false);
            $table->boolean('has_multi_branch')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
