<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

class InertiaShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_auth_user_and_company_id_are_shared_with_inertia(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->create();
        $user->companies()->attach($company->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['current_company_id' => $company->id])
            ->get('/');

        $response->assertOk();

        $response->assertInertia(function (Assert $page) use ($user, $company) {
            $page->has('auth.user')
                 ->where('auth.user.id', $user->id)
                 ->where('auth.companyId', $company->id)
                 ->etc();
        });
    }
}
