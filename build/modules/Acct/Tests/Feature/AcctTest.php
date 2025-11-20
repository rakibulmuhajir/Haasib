<?php

namespace Modules\Acct\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AcctTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the module loads correctly.
     */
    public function test_module_loads(): void
    {
        $response = $this->get('/api/acct');
        
        $response->assertStatus(200)
            ->assertJson([
                'module' => 'Acct',
                'version' => '1.0.0',
            ]);
    }
}
