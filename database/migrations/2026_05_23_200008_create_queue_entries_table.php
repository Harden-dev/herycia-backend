<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('salon_id')->constrained('salons')->cascadeOnDelete();
            $table->foreignUuid('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('service_id')->constrained('services')->restrictOnDelete();
            $table->unsignedInteger('position');
            $table->string('status');
            $table->dateTime('arrived_at');
            $table->dateTime('called_at')->nullable();
            $table->dateTime('done_at')->nullable();

            $table->index(['salon_id', 'status', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
