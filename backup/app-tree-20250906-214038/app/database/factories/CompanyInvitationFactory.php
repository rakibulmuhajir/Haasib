<?php

namespace Database\Factories;

use App\Models\CompanyInvitation;
use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CompanyInvitationFactory extends Factory
{
    protected $model = CompanyInvitation::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'invited_email' => $this->faker->unique()->safeEmail(),
            'role' => 'member',
            'invited_by_user_id' => User::factory(),
            'token' => Str::random(40),
            'status' => 'pending',
            'expires_at' => null,
        ];
    }
}
