<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FixUserCompanyRelationshipSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::find('0199489d-296b-703e-a651-0b141e2b19e0');
        $companyId = '01994cb5-bba6-70e7-a45b-03fcfa19f307';

        if ($user && ! DB::table('auth.company_user')->where('user_id', $user->id)->where('company_id', $companyId)->exists()) {
            DB::table('auth.company_user')->insert([
                'user_id' => $user->id,
                'company_id' => $companyId,
                'role' => 'admin',
                'invited_by_user_id' => $user->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->command->info("Added user {$user->email} to company {$companyId}");
        } else {
            $this->command->info('User already has access to company or user not found');
        }
    }
}
