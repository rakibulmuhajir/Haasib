<?php

namespace Modules\Invoicing\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Models\Invoice;
use App\Services\AuthService;
use App\Services\ContextService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Core\Services\ModuleService;
use Modules\Invoicing\Services\PaymentService;

class PaymentRecord extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'payment:record
        {--user= : Acting user email or UUID}
        {--company= : Company slug or UUID}
        {--invoice= : Invoice UUID (optional)}
        {--customer= : Customer UUID}
        {--amount=0 : Payment amount}
        {--method=bank_transfer : Payment method}
        {--currency=USD : Payment currency}
        {--date= : Payment date (Y-m-d or relative)}';

    protected $description = 'Record a payment, optionally linking it to an invoice.';

    public function handle(PaymentService $paymentService, ModuleService $moduleService, AuthService $authService, ContextService $contextService): int
    {
        $customerId = $this->option('customer');
        if (! $customerId) {
            $this->error('Provide a --customer UUID.');

            return self::FAILURE;
        }

        try {
            $actingUser = $this->resolveActingUser($this, $authService, $this->option('user'));
            $company = $this->resolveCompany(
                $this,
                $authService,
                $contextService,
                $actingUser,
                $this->option('company'),
                true
            );
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (! $company->hasModuleEnabled('invoicing')) {
            $this->warn("Company '{$company->name}' does not have the invoicing module enabled. Enabling it now.");
            try {
                $moduleService->enableModule($company, 'invoicing', $actingUser);
            } catch (\Throwable $e) {
                $this->error("Failed to enable invoicing module: {$e->getMessage()}");
                $this->cleanup($contextService, $actingUser);

                return self::FAILURE;
            }
        }

        $invoiceId = $this->option('invoice');
        $invoice = $invoiceId ? Invoice::where('company_id', $company->id)->find($invoiceId) : null;

        if ($invoiceId && ! $invoice) {
            $this->error("Invoice '{$invoiceId}' not found for this company.");
            $this->cleanup($contextService, $actingUser);

            return self::FAILURE;
        }

        $paymentDate = $this->option('date')
            ? Carbon::parse($this->option('date'), now()->timezone())
            : now();

        try {
            $payment = $paymentService->recordPayment([
                'company_id' => $company->id,
                'customer_id' => $customerId,
                'payment_method' => $this->option('method'),
                'amount' => (float) $this->option('amount'),
                'payment_date' => $paymentDate->toDateString(),
                'currency' => $this->option('currency'),
            ], $invoice);
        } catch (\Throwable $e) {
            $this->error("Failed to record payment: {$e->getMessage()}");
            $this->cleanup($contextService, $actingUser);

            return self::FAILURE;
        }

        $this->info("Payment {$payment->payment_number} recorded for company {$company->name}.");
        $this->table(['Field', 'Value'], [
            ['Payment ID', $payment->id],
            ['Invoice ID', $payment->paymentable_id ?? '—'],
            ['Amount', $payment->amount],
            ['Currency', $payment->currency],
            ['Status', $payment->status],
        ]);

        $this->cleanup($contextService, $actingUser);

        return self::SUCCESS;
    }

    protected function cleanup(ContextService $contextService, $actingUser): void
    {
        if ($actingUser) {
            $contextService->clearCurrentCompany($actingUser);
        }
        $contextService->clearCLICompanyContext();
    }
}
