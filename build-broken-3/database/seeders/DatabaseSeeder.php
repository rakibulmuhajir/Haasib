<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = new User();
        $superAdmin->id = '00000000-0000-0000-0000-000000000000';
        $superAdmin->name = 'Super Admin';
        $superAdmin->username = 'superadmin';
        $superAdmin->email = 'admin@haasib.com';
        $superAdmin->password = 'password';
        $superAdmin->email_verified_at = now();
        $superAdmin->save();
    }
}
