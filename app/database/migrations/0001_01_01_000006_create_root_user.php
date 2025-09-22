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
        // Create root user
        DB::table('users')->insert([
            'id' => '550e8400-e29b-41d4-a716-446655440000',
            'name' => 'Muhammad Yasir Khan',
            'email' => 'root@ferasa.org',
            'password' => bcrypt('Sup3rAdm1n2025$'),
            'system_role' => 'superadmin',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('users')->where('email', 'root@ferasa.org')->delete();
    }
};
