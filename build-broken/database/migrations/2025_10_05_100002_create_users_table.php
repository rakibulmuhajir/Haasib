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
        // Create auth schema if it doesn't exist
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        Schema::create('auth.users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('system_role')->default('user')->comment('superadmin, admin, user');
            $table->uuid('created_by_user_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->rememberToken();
            $table->timestamps();

            // Indexes
            $table->index('email');
            $table->index('username');
            $table->index('system_role');
            $table->index('is_active');
            $table->index('created_by_user_id');
            $table->index(['is_active', 'system_role']);
        });

        // Add foreign key constraint after table creation
        Schema::table('auth.users', function (Blueprint $table) {
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->onDelete('set null');
        });

        // Add RLS policy for users table
        DB::statement('
            ALTER TABLE auth.users ENABLE ROW LEVEL SECURITY;
        ');

        // Create RLS policy - users can see their own record, superadmins can see all
        DB::statement("
            CREATE POLICY users_select_policy ON auth.users
            FOR SELECT
            USING (
                id = current_setting('app.current_user_id', true)::uuid
                OR
                system_role = 'superadmin'
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Create RLS policy for updates - users can update their own record, superadmins can update all
        DB::statement("
            CREATE POLICY users_update_policy ON auth.users
            FOR UPDATE
            USING (
                id = current_setting('app.current_user_id', true)::uuid
                OR
                system_role = 'superadmin'
                OR
                current_setting('app.is_super_admin', true)::boolean = true
            );
        ");

        // Create trigger to set audit fields
        DB::statement('
            CREATE OR REPLACE FUNCTION auth.set_updated_by()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        DB::statement('
            CREATE TRIGGER users_updated_at
                BEFORE UPDATE ON auth.users
                FOR EACH ROW
                EXECUTE FUNCTION auth.set_updated_by();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop trigger and function first
        DB::statement('DROP TRIGGER IF EXISTS users_updated_at ON auth.users');
        DB::statement('DROP FUNCTION IF EXISTS auth.set_updated_by()');

        // Drop table
        Schema::dropIfExists('auth.users');
    }
};
