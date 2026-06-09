<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('salon_id')->constrained('salons')->cascadeOnDelete();
            $table->foreignUuid('appointment_id')->constrained('appointments')->restrictOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->restrictOnDelete();
            $table->unsignedInteger('amount');
            $table->string('method');
            $table->string('status');
            $table->string('mobile_money_ref')->nullable();
            $table->timestamp('paid_at')->nullable();

            $table->index(['salon_id', 'status']);
            $table->index(['client_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
