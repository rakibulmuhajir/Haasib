<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate(
            ['name' => 'super_admin', 'company_id' => null],
            ['guard_name' => 'web']
        );

        $admin = User::firstOrCreate(
            ['email' => 'admin@haasib.com'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'username' => 'superadmin',
            ]
        );

        DB::table('model_has_roles')->insertOrIgnore([
            'role_id' => $superAdminRole->id,
            'model_type' => User::class,
            'model_id' => $admin->id,
            'company_id' => null,
        ]);

        $this->command->info('Super admin created: admin@haasib.com / password');
    }
}
