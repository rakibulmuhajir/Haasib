<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create root user only if it doesn't exist
        $existingUser = DB::table('auth.users')->where('email', 'root@ferasa.org')->first();

        if (!$existingUser) {
            DB::table('auth.users')->insert([
                'id' => '550e8400-e29b-41d4-a716-446655440000',
                'name' => 'Muhammad Yasir Khan',
                'email' => 'root@ferasa.org',
                'password' => bcrypt('Sup3rAdm1n2025$'),
                'system_role' => 'superadmin',
                'email_verified_at' => now(),
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Check if the users table exists before trying to delete from it
        if (Schema::hasTable('auth.users')) {
            try {
                DB::table('auth.users')->where('email', 'root@ferasa.org')->delete();
            } catch (\Throwable $e) {
                // User might not exist or table might be in an inconsistent state
            }
        }
    }
};
