<?php

namespace Tests\Unit\Umrah;

use App\Modules\Umrah\Models\Voucher;
use PHPUnit\Framework\TestCase;

class VoucherSeparationTest extends TestCase
{
    public function test_first_individual_voucher_keeps_hotel_billing_when_source_is_archived(): void
    {
        $source = new Voucher(['billing_voucher_id' => null]);
        $source->id = '01900000-0000-7000-8000-000000000001';

        $this->assertSame([
            'billing_voucher_id' => null,
            'retain_hotel_amounts' => true,
        ], $source->separatedBillingPlan(true, 0));
    }

    public function test_additional_individual_vouchers_reference_the_single_billing_owner(): void
    {
        $source = new Voucher(['billing_voucher_id' => null]);
        $source->id = '01900000-0000-7000-8000-000000000001';

        $this->assertSame([
            'billing_voucher_id' => $source->id,
            'retain_hotel_amounts' => false,
        ], $source->separatedBillingPlan(true, 1));
    }

    public function test_separating_an_itinerary_copy_preserves_the_existing_billing_owner(): void
    {
        $source = new Voucher([
            'billing_voucher_id' => '01900000-0000-7000-8000-000000000099',
        ]);
        $source->id = '01900000-0000-7000-8000-000000000001';

        $this->assertSame([
            'billing_voucher_id' => '01900000-0000-7000-8000-000000000099',
            'retain_hotel_amounts' => false,
        ], $source->separatedBillingPlan(true, 0));
    }
}
