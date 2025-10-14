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
        Schema::create('command_executions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('command_id');
            $table->uuid('user_id');
            $table->uuid('company_id');
            $table->string('idempotency_key');
            $table->enum('status', ['pending', 'running', 'completed', 'failed']);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('parameters');
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->uuid('audit_reference')->nullable();
            $table->timestamps();

            $table->foreign('command_id')->references('id')->on('commands')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->index(['command_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('idempotency_key');
            $table->unique(['company_id', 'idempotency_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_executions');
    }
};
