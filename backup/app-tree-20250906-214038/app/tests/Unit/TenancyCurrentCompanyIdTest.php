<?php

namespace Tests\Unit;

use App\Support\Tenancy;
use Tests\TestCase;

class TenancyCurrentCompanyIdTest extends TestCase
{
    public function test_returns_bound_company_id(): void
    {
        app()->instance('tenant.company_id', 'company-123');

        $this->assertSame('company-123', app(Tenancy::class)->currentCompanyId());
    }

    public function test_returns_null_when_no_company_bound(): void
    {
        app()->forgetInstance('tenant.company_id');

        $this->assertNull(app(Tenancy::class)->currentCompanyId());
    }
}
