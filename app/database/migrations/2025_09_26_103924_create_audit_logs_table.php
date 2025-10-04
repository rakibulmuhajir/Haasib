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
        Schema::create('acct.audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('auth.users')->nullOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('auth.companies')->nullOnDelete();
            $table->string('action');
            $table->json('params')->nullable();
            $table->json('result')->nullable();
            $table->string('idempotency_key')->nullable();
            $table->timestamps();

            // Add indexes for better performance
            $table->index(['user_id', 'created_at']);
            $table->index(['company_id', 'created_at']);
            $table->index('action');
            $table->index('idempotency_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acct.audit_logs');
    }
};
