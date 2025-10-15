<?php

namespace Tests\Feature\Accounting\Customers;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Accounting\Domain\Customers\Actions\AdjustCustomerCreditLimitAction;
use Modules\Accounting\Domain\Customers\Models\Customer;
use Modules\Accounting\Domain\Customers\Models\CustomerCreditLimit;
use Tests\TestCase;

class AdjustCustomerCreditLimitTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->customer = Customer::factory()->create([
            'credit_limit' => 1000.00,
            'credit_limit_effective_at' => now(),
        ]);
    }

    /** @test */
    public function it_can_adjust_customer_credit_limit_with_approval()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;
        $newLimit = 2500.00;
        $effectiveDate = now()->addDay();

        // Act
        $creditLimit = $action->execute(
            $this->customer,
            $newLimit,
            $effectiveDate,
            [
                'reason' => 'Increased credit limit for long-term customer',
                'approval_reference' => 'APPROVAL-123',
                'changed_by_user_id' => $this->user->id,
            ]
        );

        // Assert
        $this->assertInstanceOf(CustomerCreditLimit::class, $creditLimit);
        $this->assertEquals($this->customer->id, $creditLimit->customer_id);
        $this->assertEquals($newLimit, $creditLimit->limit_amount);
        $this->assertEquals($effectiveDate, $creditLimit->effective_at);
        $this->assertEquals('approved', $creditLimit->status);
        $this->assertEquals('Increased credit limit for long-term customer', $creditLimit->reason);
        $this->assertEquals('APPROVAL-123', $creditLimit->approval_reference);

        // Verify customer record is updated
        $this->customer->refresh();
        $this->assertEquals($newLimit, $this->customer->credit_limit);
        $this->assertEquals($effectiveDate, $this->customer->credit_limit_effective_at);
    }

    /** @test */
    public function it_creates_pending_credit_limit_requiring_approval()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;
        $newLimit = 5000.00; // High amount requiring approval

        // Act
        $creditLimit = $action->execute(
            $this->customer,
            $newLimit,
            now(),
            [
                'status' => 'pending',
                'reason' => 'High credit limit request',
                'changed_by_user_id' => $this->user->id,
            ]
        );

        // Assert
        $this->assertEquals('pending', $creditLimit->status);
        $this->assertNull($creditLimit->approval_reference);

        // Customer record should not be updated for pending limits
        $this->customer->refresh();
        $this->assertEquals(1000.00, $this->customer->credit_limit); // Original value
    }

    /** @test */
    public function it_validates_credit_limit_amount_cannot_be_negative()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Credit limit amount cannot be negative');

        $action->execute(
            $this->customer,
            -500.00,
            now(),
            ['changed_by_user_id' => $this->user->id]
        );
    }

    /** @test */
    public function it_validates_effective_date_cannot_be_in_past_for_approved_limits()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;
        $pastDate = now()->subDay();

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Effective date for approved credit limits cannot be in the past');

        $action->execute(
            $this->customer,
            2000.00,
            $pastDate,
            [
                'status' => 'approved',
                'changed_by_user_id' => $this->user->id,
            ]
        );
    }

    /** @test */
    public function it_tracks_credit_limit_history_correctly()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;

        // Create first credit limit
        $firstLimit = $action->execute(
            $this->customer,
            1500.00,
            now()->subDays(10),
            ['changed_by_user_id' => $this->user->id]
        );

        // Create second credit limit
        $secondLimit = $action->execute(
            $this->customer,
            3000.00,
            now(),
            ['changed_by_user_id' => $this->user->id]
        );

        // Assert
        $this->assertEquals(2, CustomerCreditLimit::where('customer_id', $this->customer->id)->count());

        // Verify chronological order
        $history = CustomerCreditLimit::where('customer_id', $this->customer->id)
            ->orderBy('effective_at', 'desc')
            ->get();

        $this->assertEquals(3000.00, $history->first()->limit_amount);
        $this->assertEquals(1500.00, $history->last()->limit_amount);
    }

    /** @test */
    public function it_handles_future_dated_credit_limits()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;
        $futureDate = now()->addDays(30);

        // Act
        $futureLimit = $action->execute(
            $this->customer,
            4000.00,
            $futureDate,
            ['changed_by_user_id' => $this->user->id]
        );

        // Assert
        $this->assertEquals($futureDate, $futureLimit->effective_at);

        // Customer record should reflect current active limit, not future one
        $this->customer->refresh();
        $this->assertEquals(1000.00, $this->customer->credit_limit); // Original still active
    }

    /** @test */
    public function it_creates_audit_entry_for_credit_limit_adjustments()
    {
        // Arrange
        $action = new AdjustCustomerCreditLimitAction;

        // Act
        $creditLimit = $action->execute(
            $this->customer,
            2500.00,
            now(),
            [
                'reason' => 'Credit limit adjustment',
                'changed_by_user_id' => $this->user->id,
            ]
        );

        // Assert - Check that audit log contains the credit limit adjustment
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'credit_limit_adjusted',
            'subject_type' => Customer::class,
            'subject_id' => $this->customer->id,
            'user_id' => $this->user->id,
        ]);
    }
}
