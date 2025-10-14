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
        Schema::create('command_analytics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('command_id');
            $table->uuid('user_id')->nullable();
            $table->string('environment');
            $table->timestamp('executed_at');
            $table->string('execution_status'); // success, failure, timeout
            $table->integer('execution_time_ms')->nullable();
            $table->json('parameters_used')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('command_id')->references('id')->on('commands')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('set null');

            $table->index(['company_id', 'command_id', 'executed_at']);
            $table->index(['company_id', 'user_id', 'executed_at']);
            $table->index(['environment', 'executed_at']);
            $table->index(['execution_status', 'executed_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('command_analytics');
    }
};
