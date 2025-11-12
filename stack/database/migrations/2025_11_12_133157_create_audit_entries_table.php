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
        Schema::create('audit.entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id')->nullable();
            $table->uuid('user_id')->nullable();
            $table->string('event'); // created, updated, deleted, etc.
            $table->string('model_type'); // The auditable model class
            $table->uuid('model_id')->nullable(); // The auditable model ID
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('tags')->nullable(); // For categorizing audit events
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes for performance
            $table->index(['company_id']);
            $table->index(['user_id']);
            $table->index(['event']);
            $table->index(['model_type', 'model_id']);
            $table->index(['created_at']);

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->onDelete('set null');

            $table->foreign('user_id')
                ->references('id')
                ->on('auth.users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit.entries');
    }
};
