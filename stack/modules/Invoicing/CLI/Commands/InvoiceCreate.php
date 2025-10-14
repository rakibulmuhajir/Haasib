<?php

namespace Modules\Invoicing\CLI\Commands;

use App\Console\Concerns\InteractsWithCliContext;
use App\Services\AuthService;
use App\Services\ContextService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Core\Services\ModuleService;
use Modules\Invoicing\Services\InvoiceService;

class InvoiceCreate extends Command
{
    use InteractsWithCliContext;

    protected $signature = 'invoice:create
        {--user= : Acting user email or UUID}
        {--company= : Company slug or UUID}
        {--customer= : Customer UUID}
        {--number= : Invoice number (generated if omitted)}
        {--amount=0 : Line item amount}
        {--description=Services : Line item description}
        {--currency=USD : Invoice currency}
        {--due= : Due date (Y-m-d or relative like +30 days)}';

    protected $description = 'Create an invoice with a single line item.';

    public function handle(InvoiceService $invoiceService, ModuleService $moduleService, AuthService $authService, ContextService $contextService): int
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

        $issueDate = now();
        $dueOption = $this->option('due');
        $dueDate = $dueOption
            ? Carbon::parse($dueOption, $issueDate->timezone())
            : $issueDate->copy()->addDays(30);

        $invoiceNumber = $this->option('number') ?: sprintf('INV-%s-%04d', $issueDate->format('Y'), random_int(1, 9999));
        $amount = (float) $this->option('amount');

        $lineItem = [
            'description' => $this->option('description'),
            'quantity' => 1,
            'unit_price' => $amount,
            'discount_amount' => 0,
            'tax_amount' => 0,
        ];

        try {
            $invoice = $invoiceService->createInvoice([
                'company_id' => $company->id,
                'customer_id' => $customerId,
                'invoice_number' => $invoiceNumber,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'currency' => $this->option('currency'),
                'status' => 'draft',
            ], [$lineItem]);
        } catch (\Throwable $e) {
            $this->error("Failed to create invoice: {$e->getMessage()}");
            $this->cleanup($contextService, $actingUser);

            return self::FAILURE;
        }

        $this->info("Invoice {$invoice->invoice_number} created for company {$company->name}.");
        $this->table(['Field', 'Value'], [
            ['Invoice ID', $invoice->id],
            ['Customer ID', $invoice->customer_id],
            ['Issue Date', $invoice->issue_date->toDateString()],
            ['Due Date', $invoice->due_date->toDateString()],
            ['Total', $invoice->total_amount],
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
