<?php // tests/Feature/Console/ArtisanCommandsTest.php
namespace Tests\Feature\Console;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ArtisanCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_add_and_assign_flow(): void
    {
        $this->artisan('user:add', ['--name'=>'Jane','--email'=>'jane@example.com'])
            ->assertExitCode(0);
        $this->artisan('company:add', ['--name'=>'Acme'])->assertExitCode(0);
        $this->artisan('company:assign', ['--email'=>'jane@example.com','--company'=>'Acme','--role'=>'admin'])
            ->assertExitCode(0);

        $this->assertDatabaseHas('auth.company_user', [
            'role' => 'admin',
        ]);
    }

    public function test_unassign_and_delete(): void
    {
        $this->artisan('user:add', ['--name'=>'Jane','--email'=>'jane@example.com']);
        $this->artisan('company:add', ['--name'=>'BetaCo']);
        $this->artisan('company:assign', ['--email'=>'jane@example.com','--company'=>'BetaCo','--role'=>'viewer']);
        $this->artisan('company:unassign', ['--email'=>'jane@example.com','--company'=>'BetaCo']);
        $this->artisan('user:delete', ['--email'=>'jane@example.com'])->assertExitCode(0);
        $this->artisan('company:delete', ['--company'=>'BetaCo'])->assertExitCode(0);

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('companies', 0);
    }
}
