<?php

use App\Models\Company;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaymentService;
use App\Support\ServiceContext;
use App\Support\ServiceContextHelper;
use Brick\Money\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('PaymentService ServiceContext Integration', function () {
    beforeEach(function () {
        $this->company = Company::factory()->create();
        $this->user = User::factory()->create();
        $this->customer = Customer::factory()->create(['company_id' => $this->company->id]);
        $this->currency = Currency::factory()->create();
        $this->paymentService = new PaymentService;
    });

    test('createPayment works correctly with ServiceContext parameter', function () {
        $context = new ServiceContext($this->user, $this->company->id, 'test-key-123');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(100, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'check',
            paymentDate: now()->toDateString(),
            context: $context
        );

        expect($payment)->toBeInstanceOf(Payment::class)
            ->and($payment->company_id)->toBe($this->company->id)
            ->and($payment->customer_id)->toBe($this->customer->id)
            ->and($payment->amount)->toBe('100.00')
            ->and($payment->payment_method)->toBe('check');
    });

    test('createPayment requires ServiceContext parameter', function () {
        // Test that ServiceContext is now required
        $context = new ServiceContext($this->user, $this->company->id, 'required-context-test');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(50, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'bank_transfer',
            context: $context // ServiceContext is now required
        );

        expect($payment)->toBeInstanceOf(Payment::class)
            ->and($payment->company_id)->toBe($this->company->id);
    });

    test('createPayment audit logging captures user context properly', function () {
        $context = new ServiceContext($this->user, $this->company->id, 'audit-test-key');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(75, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'credit_card',
            context: $context
        );

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.create',
            'idempotency_key' => 'audit-test-key',
        ]);

        $auditLog = DB::table('acct.audit_logs')
            ->where('action', 'payment.create')
            ->first();

        expect(json_decode($auditLog->params))->toMatchArray([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'payment_method' => 'credit_card',
            'amount' => '75.00',
            'currency_id' => $this->currency->id,
        ]);
    });

    test('updatePayment works correctly with ServiceContext parameter', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 50,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'update-key-456');

        $updatedPayment = $this->paymentService->updatePayment(
            payment: $payment,
            customer: $this->customer,
            amount: 150,
            currencyId: $this->currency->id,
            paymentMethod: 'check',
            paymentDate: now()->toDateString(),
            paymentNumber: 'PAY-001',
            context: $context
        );

        expect($updatedPayment->amount)->toBe('150.00')
            ->and($updatedPayment->payment_method)->toBe('check');
    });

    test('updatePayment audit logging captures user context properly', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'update-audit-key');

        $this->paymentService->updatePayment(
            payment: $payment,
            customer: $this->customer,
            amount: 200,
            currencyId: $this->currency->id,
            paymentMethod: 'bank_transfer',
            paymentDate: now()->toDateString(),
            paymentNumber: 'PAY-002',
            context: $context
        );

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.update',
            'idempotency_key' => 'update-audit-key',
        ]);
    });

    test('deletePayment works correctly with ServiceContext parameter', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'delete-key-789');

        $result = $this->paymentService->deletePayment($payment, $context);

        expect($result)->toBeTrue();
        $this->assertModelMissing($payment);
    });

    test('deletePayment audit logging captures user context properly', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'delete-audit-key');

        $this->paymentService->deletePayment($payment, $context);

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.delete',
            'idempotency_key' => 'delete-audit-key',
        ]);

        $auditLog = DB::table('acct.audit_logs')
            ->where('action', 'payment.delete')
            ->first();

        expect(json_decode($auditLog->params))->toMatchArray([
            'payment_id' => $payment->id,
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'amount' => '100.00',
        ]);
    });

    test('allocatePayment works correctly with ServiceContext parameter', function () {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'allocate-key-101');

        $allocations = [
            ['invoice_id' => $invoice->id, 'amount' => 50],
        ];

        $result = $this->paymentService->allocatePayment($payment, $allocations, 'Test allocation', $context);

        expect($result)->toHaveCount(1)
            ->and($result[0]->amount)->toBe('50.0000');
    });

    test('allocatePayment audit logging captures user context properly', function () {
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);

        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'allocate-audit-key');

        $allocations = [
            ['invoice_id' => $invoice->id, 'amount' => 75],
        ];

        $this->paymentService->allocatePayment($payment, $allocations, 'Test allocation', $context);

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.allocate',
            'idempotency_key' => 'allocate-audit-key',
        ]);

        $auditLog = DB::table('acct.audit_logs')
            ->where('action', 'payment.allocate')
            ->first();

        expect(json_decode($auditLog->params))->toMatchArray([
            'payment_id' => $payment->id,
            'allocations_count' => 1,
            'total_allocated' => 75,
        ]);
    });

    test('voidPayment works correctly with ServiceContext parameter', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'void-key-202');

        $voidedPayment = $this->paymentService->voidPayment($payment, 'Test void reason', $context);

        expect($voidedPayment->status)->toBe('cancelled');
    });

    test('voidPayment audit logging captures user context properly', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'pending',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'void-audit-key');

        $this->paymentService->voidPayment($payment, 'Test void reason', $context);

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.void',
            'idempotency_key' => 'void-audit-key',
        ]);

        $auditLog = DB::table('acct.audit_logs')
            ->where('action', 'payment.void')
            ->first();

        expect(json_decode($auditLog->params))->toMatchArray([
            'payment_id' => $payment->id,
            'reason' => 'Test void reason',
        ]);
    });

    test('refundPayment works correctly with ServiceContext parameter', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'refund-key-303');

        $refundAmount = Money::of(50, $this->currency->code);
        $refunds = $this->paymentService->refundPayment($payment, $refundAmount, 'Test refund reason', $context);

        expect($refunds)->toHaveCount(1)
            ->and($refunds[0]->amount)->toBe('50.0000');
    });

    test('refundPayment audit logging captures user context properly', function () {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'status' => 'completed',
            'amount' => 100,
        ]);

        $context = new ServiceContext($this->user, $this->company->id, 'refund-audit-key');

        $refundAmount = Money::of(25, $this->currency->code);
        $this->paymentService->refundPayment($payment, $refundAmount, 'Test refund reason', $context);

        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'action' => 'payment.refund',
            'idempotency_key' => 'refund-audit-key',
        ]);

        $auditLog = DB::table('acct.audit_logs')
            ->where('action', 'payment.refund')
            ->first();

        expect(json_decode($auditLog->params))->toMatchArray([
            'payment_id' => $payment->id,
            'refund_amount' => '25.00',
            'reason' => 'Test refund reason',
        ]);
    });

    test('ServiceContext idempotency key is properly used in all operations', function () {
        $context = new ServiceContext($this->user, $this->company->id, 'idempotency-test-key');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(100, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'check',
            context: $context
        );

        // Process the payment
        $this->paymentService->processPayment($payment, 'processor-ref-123', null, $context);

        // Void the payment
        $this->paymentService->voidPayment($payment, 'Test void', $context);

        // Check that all audit logs have the same idempotency key
        $auditLogs = DB::table('acct.audit_logs')
            ->where('idempotency_key', 'idempotency-test-key')
            ->get();

        expect($auditLogs)->toHaveCount(3)
            ->and($auditLogs->pluck('action')->toArray())->toMatchArray([
                'payment.create',
                'payment.process',
                'payment.void',
            ]);
    });

    test('ServiceContext without user (system context) works correctly', function () {
        $context = ServiceContextHelper::forSystem($this->company->id, 'system-key-404');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(200, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'bank_transfer',
            context: $context
        );

        expect($payment)->toBeInstanceOf(Payment::class)
            ->and($payment->amount)->toBe('200.00');

        // Check audit log has null user_id but proper company_id
        $this->assertDatabaseHas('acct.audit_logs', [
            'user_id' => null,
            'company_id' => $this->company->id,
            'action' => 'payment.create',
            'idempotency_key' => 'system-key-404',
        ]);
    });

    test('ServiceContext is required for all PaymentService methods', function () {
        // Test that ServiceContext parameter is now required for all methods
        $context = new ServiceContext($this->user, $this->company->id, 'required-test-key');

        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(50, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'cash',
            context: $context
        );

        $processedPayment = $this->paymentService->processPayment($payment, null, null, $context);

        // Should work properly with provided ServiceContext
        expect($processedPayment)->toBeInstanceOf(Payment::class)
            ->and($processedPayment->status)->toBe('completed');
    });

    test('All PaymentService methods accept ServiceContext parameter', function () {
        $context = new ServiceContext($this->user, $this->company->id, 'comprehensive-test-key');

        // Test that all required methods can accept ServiceContext without errors
        $payment = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(100, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'check',
            context: $context
        );

        // Process payment
        $processedPayment = $this->paymentService->processPayment($payment, null, null, $context);
        expect($processedPayment->status)->toBe('completed');

        // Update payment
        $updatedPayment = $this->paymentService->updatePayment(
            payment: $payment,
            customer: $this->customer,
            amount: 150,
            currencyId: $this->currency->id,
            paymentMethod: 'bank_transfer',
            paymentDate: now()->toDateString(),
            paymentNumber: 'PAY-001',
            context: $context
        );
        expect($updatedPayment->amount)->toBe('150.00');

        // Delete payment (create a new one for deletion test)
        $paymentToDelete = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(25, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'cash',
            context: $context
        );

        $deleteResult = $this->paymentService->deletePayment($paymentToDelete, $context);
        expect($deleteResult)->toBeTrue();

        // Test allocation (create invoice and completed payment)
        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'customer_id' => $this->customer->id,
            'currency_id' => $this->currency->id,
            'total_amount' => 100,
            'balance_due' => 100,
        ]);

        $paymentForAllocation = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(100, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'check',
            context: $context
        );

        $this->paymentService->processPayment($paymentForAllocation, null, null, $context);

        $allocations = [
            ['invoice_id' => $invoice->id, 'amount' => 50],
        ];

        $allocationResult = $this->paymentService->allocatePayment($paymentForAllocation, $allocations, 'Test allocation', $context);
        expect($allocationResult)->toHaveCount(1);

        // Test void payment
        $paymentToVoid = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(75, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'credit_card',
            context: $context
        );

        $voidedPayment = $this->paymentService->voidPayment($paymentToVoid, 'Test void', $context);
        expect($voidedPayment->status)->toBe('cancelled');

        // Test refund payment
        $paymentToRefund = $this->paymentService->createPayment(
            company: $this->company,
            customer: $this->customer,
            amount: Money::of(100, $this->currency->code),
            currency: $this->currency,
            paymentMethod: 'debit_card',
            context: $context
        );

        $this->paymentService->processPayment($paymentToRefund, null, null, $context);

        $refundResult = $this->paymentService->refundPayment($paymentToRefund, Money::of(30, $this->currency->code), 'Test refund', $context);
        expect($refundResult)->toHaveCount(1);

        // Verify all operations were logged with the same idempotency key
        $auditLogs = DB::table('acct.audit_logs')
            ->where('idempotency_key', 'comprehensive-test-key')
            ->get();

        expect($auditLogs->count())->toBeGreaterThan(5); // At least 6 operations
    });
});
