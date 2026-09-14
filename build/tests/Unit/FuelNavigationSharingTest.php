<?php

namespace Tests\Unit;

use App\Facades\CompanyContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\User;
use App\Modules\FuelStation\Services\FuelNavigationAccess;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FuelNavigationSharingTest extends TestCase
{
    public static function companyContexts(): array
    {
        return [
            'travel route with remembered fuel company' => ['umrah', [], false],
            'fuel industry route' => ['fuel_station', [], true],
            'fuel module route' => ['retail', ['fuel_station' => true], true],
            'global route without tenant context' => [null, [], false],
        ];
    }

    #[DataProvider('companyContexts')]
    public function test_navigation_uses_resolved_route_context_instead_of_display_row(?string $industry, array $modules, bool $expectsFuel): void
    {
        CompanyContext::shouldReceive('getCompany')->once()->andReturn(null);
        $context = app(CurrentCompany::class);
        $context->clear();

        $user = new User;
        $user->id = '00000000-0000-0000-0000-000000000000';
        $request = Request::create('/current-company');
        $request->setUserResolver(fn () => $user);
        $request->setLaravelSession(app('session.store'));

        $row = (object) [
            'id' => '10000000-0000-0000-0000-000000000001',
            'slug' => 'remembered-station',
            'industry_code' => 'fuel_station',
            'settings' => '{"modules":{"fuel_station":true}}',
        ];
        $query = Mockery::mock();
        DB::shouldReceive('table')->once()->with('auth.companies as c')->andReturn($query);
        $query->shouldReceive('where', 'select', 'orderBy')->andReturnSelf();
        $query->shouldReceive('get')->once()->andReturn(collect([$row]));

        $shared = app(HandleInertiaRequests::class)->share($request);
        $this->assertSame('remembered-station', $shared['auth']['currentCompany']['slug']);

        $company = null;
        if ($industry !== null) {
            $company = new Company;
            $company->id = '10000000-0000-0000-0000-000000000002';
            $company->industry_code = $industry;
            $company->settings = ['modules' => $modules];
            // Simulate tenant middleware running after the Inertia middleware.
            $context->set($company);
        }

        $navigation = Mockery::mock(FuelNavigationAccess::class);
        $this->app->instance(FuelNavigationAccess::class, $navigation);
        $expected = ['allowed' => ['dailyClose'], 'hasInvestors' => false];
        if ($expectsFuel) {
            $navigation->shouldReceive('forUser')->once()->with($company, $user)->andReturn($expected);
        } else {
            $navigation->shouldNotReceive('forUser');
        }

        $this->assertSame($expectsFuel ? $expected : null, ($shared['auth']['fuelNavigation'])());
        $context->clear();
    }
}
