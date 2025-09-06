<?php // tests/Feature/DevCliHttpTest.php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Support\CommandBus;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DevCliHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_cli_executes_commands_when_enabled(): void
    {
        config()->set('app.dev_console_enabled', true);
        $user = User::factory()->create();

        $mock = \Mockery::mock(CommandBus::class);
        $mock->shouldReceive('dispatch')
            ->with('user.create', [
                'name' => 'X',
                'email' => 'x@example.com',
                'password' => null,
            ], \Mockery::type(User::class))
            ->andReturn(['user' => ['email' => 'x@example.com']]);
        $this->instance(CommandBus::class, $mock);

        $this->actingAs($user)
            ->postJson('/dev/cli/execute', ['command' => 'user:add --name="X" --email=x@example.com'])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('output.user.email', 'x@example.com');
    }

    public function test_dev_cli_is_forbidden_when_disabled(): void
    {
        config()->set('app.dev_console_enabled', false);
        $user = User::factory()->create();
        $this->actingAs($user)->get('/dev/cli')->assertStatus(403);
    }
}
