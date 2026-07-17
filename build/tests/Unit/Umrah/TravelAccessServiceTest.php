<?php

namespace Tests\Unit\Umrah;

use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\TravelAccessService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TravelAccessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_group_is_locked_for_agents_when_travel_date_has_started(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $group = new VisaGroup(['travel_date' => '2026-07-16']);
        $group->setRelation('vouchers', new Collection);

        $this->assertTrue(app(TravelAccessService::class)->groupHasStarted($group));
    }

    public function test_future_voucher_departure_remains_editable_until_departure(): void
    {
        Carbon::setTestNow('2026-07-16 10:00:00');
        $voucher = new Voucher([
            'service_bundle' => Voucher::SERVICE_VISA_TRANSPORT,
            'onward_departure_at' => '2026-07-16 12:00:00',
            'hotel_stays' => [],
        ]);

        $this->assertFalse(app(TravelAccessService::class)->voucherHasStarted($voucher));
    }

    public function test_hotel_only_voucher_locks_at_first_check_in_date(): void
    {
        Carbon::setTestNow('2026-07-16 00:01:00');
        $voucher = new Voucher([
            'service_bundle' => Voucher::SERVICE_HOTEL,
            'hotel_stays' => [
                ['check_in_date' => '2026-07-17'],
                ['check_in_date' => '2026-07-16'],
            ],
        ]);

        $this->assertTrue(app(TravelAccessService::class)->voucherHasStarted($voucher));
    }

    public function test_role_matrix_gives_accountants_payment_access_and_agents_read_only_payment_access(): void
    {
        $roles = require base_path('config/role-permissions.php');

        $this->assertContains('umrah.payment.view', $roles['accountant']);
        $this->assertContains('umrah.payment.create', $roles['accountant']);
        $this->assertContains('umrah.payment.view', $roles['agent']);
        $this->assertNotContains('umrah.payment.create', $roles['agent']);
        $this->assertNotContains('umrah.report.view', $roles['agent']);
        $this->assertContains('umrah.report.own.view', $roles['agent']);
        $this->assertNotContains('umrah.vendor.view', $roles['agent']);
        $this->assertNotContains('account.view', $roles['agent']);
        $this->assertNotContains('umrah.group.view', $roles['member']);
    }
}
