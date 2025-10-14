<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Company;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{

public function run(): void {
    // Reference data first (idempotent)
    $this->call(ReferenceDataSeeder::class);
    $user = User::factory()->create(['email'=>'founder@example.com']);
    $acme = Company::factory()->create(['name'=>'Acme']);
    $beta = Company::factory()->create(['name'=>'BetaCo']);
    $user->companies()->attach($acme->id, ['role'=>'owner']);
    $user->companies()->attach($beta->id, ['role'=>'viewer']);
}

}
