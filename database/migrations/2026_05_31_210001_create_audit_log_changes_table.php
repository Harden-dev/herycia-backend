<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('audit_log_id');
            $table->string('field', 100);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->timestamps();

            $table->foreign('audit_log_id')->references('id')->on('audit_logs')->onDelete('cascade');
            $table->index('audit_log_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_log_changes');
    }
};
