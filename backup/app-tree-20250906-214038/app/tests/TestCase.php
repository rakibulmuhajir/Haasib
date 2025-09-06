<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    /**
     * Make Gate/Tenancy see a company for this test.
     */
    protected function setTenant(?string $companyId): void
    {
        if ($companyId) {
            // Scope to current transaction if one exists; otherwise to the session.
            DB::select("select set_config('app.current_company_id', ?, true)", [$companyId]);
            app()->instance('tenant.company_id', $companyId);
        } else {
            // Optional: clear it
            DB::unprepared("reset app.current_company_id");
            app()->forgetInstance('tenant.company_id');
        }
    }
}
