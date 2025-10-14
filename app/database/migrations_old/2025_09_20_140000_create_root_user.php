<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        // Create the root user
        DB::table('users')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000', // Fixed UUID for consistency
            'name' => 'Muhammad Yasir Khan',
            'email' => 'root@ferasa.org',
            'email_verified_at' => now(),
            'password' => Hash::make('Sup3rAdm1n2025$'),
            'system_role' => 'superadmin',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Remove the root user
        DB::table('users')->where('email', 'root@ferasa.org')->delete();
    }
};
