<?php

namespace Tests\Unit\Umrah;

use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\Voucher;
use Tests\TestCase;

class CorrectionWorkflowTest extends TestCase
{
    public function test_vouchers_support_controlled_cancellation_without_adding_a_group_lifecycle(): void
    {
        $this->assertSame([
            Voucher::STATUS_DRAFT => 'Draft',
            Voucher::STATUS_APPROVED => 'Approved',
            Voucher::STATUS_CANCELLED => 'Cancelled',
        ], Voucher::STATUSES);
    }

    public function test_payments_have_posted_and_reversed_states(): void
    {
        $this->assertSame('posted', GroupPayment::STATUS_POSTED);
        $this->assertSame('reversed', GroupPayment::STATUS_REVERSED);
    }

    public function test_accountants_can_reverse_payments_and_agents_cannot_reverse_or_cancel(): void
    {
        $roles = require base_path('config/role-permissions.php');

        $this->assertContains('umrah.payment.reverse', $roles['accountant']);
        $this->assertNotContains('umrah.payment.reverse', $roles['agent']);
        $this->assertNotContains('umrah.voucher.cancel', $roles['agent']);
    }
}
