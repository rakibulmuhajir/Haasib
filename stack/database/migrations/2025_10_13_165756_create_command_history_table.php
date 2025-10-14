<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('command_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('command_id');
            $table->uuid('company_id');
            $table->timestamp('executed_at');
            $table->text('input_text');
            $table->json('parameters_used');
            $table->enum('execution_status', ['success', 'failed', 'partial']);
            $table->text('result_summary')->nullable();
            $table->uuid('audit_reference')->nullable();
            $table->timestamps();

            $table->foreign('command_id')->references('id')->on('commands')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->index(['user_id', 'executed_at']);
            $table->index(['command_id', 'executed_at']);
            $table->index(['company_id', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_history');
    }
};
