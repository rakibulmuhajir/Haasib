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
        Schema::create('auth.invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('inviter_user_id');
            $table->string('email');
            $table->string('role');
            $table->string('token', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->enum('status', ['pending', 'accepted', 'declined', 'expired'])->default('pending');
            $table->json('metadata')->nullable(); // Store additional invitation data
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            $table->foreign('inviter_user_id')->references('id')->on('auth.users')->onDelete('cascade');

            // Indexes for performance
            $table->index(['email', 'status']);
            $table->index(['company_id', 'status']);
            $table->index(['token', 'expires_at']);
            $table->index('status');

            // Unique constraint to prevent duplicate pending invitations
            $table->unique(['company_id', 'email', 'status'], 'unique_pending_invitation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth.invitations');
    }
};
