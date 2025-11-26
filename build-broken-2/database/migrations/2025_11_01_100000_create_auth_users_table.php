<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create auth.users table with UUID
        Schema::create('auth.users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            
            // Additional fields
            $table->string('avatar')->nullable();
            $table->string('timezone')->default('UTC');
            $table->string('locale')->default('en');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            
            // Indexes
            $table->index('email');
            $table->index('is_active');
        });

        // Enable RLS on users table
        DB::statement('ALTER TABLE auth.users ENABLE ROW LEVEL SECURITY;');

        // Simple RLS policies - detailed policies will be added after company_user table exists
        DB::statement("
            CREATE POLICY users_select_policy ON auth.users
            FOR SELECT
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                id = current_setting('app.current_user_id', true)::uuid
            );
        ");

        DB::statement("
            CREATE POLICY users_update_policy ON auth.users
            FOR UPDATE
            USING (
                current_setting('app.is_super_admin', true)::boolean = true OR
                id = current_setting('app.current_user_id', true)::uuid
            );
        ");

        DB::statement("
            CREATE POLICY users_insert_policy ON auth.users
            FOR INSERT
            WITH CHECK (true);
        ");

        DB::statement("
            CREATE POLICY users_delete_policy ON auth.users
            FOR DELETE
            USING (current_setting('app.is_super_admin', true)::boolean = true);
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('auth.users');
    }
};
