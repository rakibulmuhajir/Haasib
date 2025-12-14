<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('auth.company_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('email', 255);
            $table->string('role', 20);
            $table->string('token', 255)->unique();
            $table->uuid('invited_by_user_id');
            $table->uuid('accepted_by_user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->onDelete('cascade');

            $table->foreign('invited_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('restrict');

            $table->foreign('accepted_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');

            // Indexes
            $table->index('company_id');
            $table->index('email');
            $table->index('token');
            $table->index('status');
            $table->index('expires_at');
            $table->index(['company_id', 'status']);
            $table->index(['email', 'status']);
        });

        // Add check constraints - matching company_user roles
        DB::statement("
            ALTER TABLE auth.company_invitations
            ADD CONSTRAINT valid_role
            CHECK (role IN ('owner', 'admin', 'accountant', 'viewer', 'member'))
        ");

        DB::statement("
            ALTER TABLE auth.company_invitations
            ADD CONSTRAINT valid_status
            CHECK (status IN ('pending', 'accepted', 'rejected', 'expired', 'revoked'))
        ");

        // Note: RLS is NOT enabled on this table because:
        // - Invited users need to see invitations before they're company members
        // - Access is controlled at application level via explicit email/company_id filters
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('auth.company_invitations');
    }
};
