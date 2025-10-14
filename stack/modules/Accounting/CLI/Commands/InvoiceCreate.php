<?php

namespace Modules\Accounting\CLI\Commands;

use App\Services\ContextService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class InvoiceCreate extends Command
{
    protected $signature = 'acc:invoice:create
                            {customer : Customer name or email}
                            {amount : Invoice amount}
                            {--due= : Due date (Y-m-d, defaults to +30 days)}
                            {--description= : Invoice description}
                            {--items=* : Invoice items (format: "Description:Quantity:Price")}';

    protected $description = 'Create a new invoice (Accounting module)';

    public function handle(ContextService $contextService): int
    {
        $customer = $this->argument('customer');
        $amount = (float) $this->argument('amount');
        $dueDate = $this->option('due') ?: now()->addDays(30)->format('Y-m-d');
        $description = $this->option('description') ?: 'Invoice for services rendered';
        $items = $this->option('items');

        // Check for user context
        $currentUser = $contextService->getCurrentUser();
        if (! $currentUser) {
            $this->error('No active user context. Please set user context first.');

            return 1;
        }

        // Check for company context
        $currentCompany = $contextService->getCurrentCompany();
        if (! $currentCompany) {
            $this->error('No active company context. Please set company context first.');

            return 1;
        }

        // Validate amount
        if ($amount <= 0) {
            $this->error('Amount must be greater than 0.');

            return 1;
        }

        // Validate due date
        if (! strtotime($dueDate)) {
            $this->error('Invalid due date format. Use Y-m-d format.');

            return 1;
        }

        // Parse items if provided
        $invoiceItems = [];
        $totalAmount = 0;

        if (! empty($items)) {
            foreach ($items as $item) {
                $parts = explode(':', $item);
                if (count($parts) !== 3) {
                    $this->error("Invalid item format: '{$item}'. Use 'Description:Quantity:Price'");

                    return 1;
                }

                $itemDescription = trim($parts[0]);
                $quantity = (float) $parts[1];
                $price = (float) $parts[2];

                if ($quantity <= 0 || $price <= 0) {
                    $this->error("Quantity and price must be greater than 0 for item: '{$item}'");

                    return 1;
                }

                $invoiceItems[] = [
                    'description' => $itemDescription,
                    'quantity' => $quantity,
                    'unit_price' => $price,
                    'total' => $quantity * $price,
                ];

                $totalAmount += $quantity * $price;
            }
        } else {
            // Create default item
            $invoiceItems[] = [
                'description' => $description,
                'quantity' => 1,
                'unit_price' => $amount,
                'total' => $amount,
            ];
            $totalAmount = $amount;
        }

        try {
            DB::beginTransaction();

            // Generate invoice number
            $invoiceNumber = 'INV-'.date('Y').'-'.str_pad((string) mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

            // Create invoice header
            $invoiceId = DB::table('acct.invoices')->insertGetId([
                'company_id' => $currentCompany->id,
                'invoice_number' => $invoiceNumber,
                'customer_name' => $customer,
                'customer_email' => filter_var($customer, FILTER_VALIDATE_EMAIL) ? $customer : null,
                'issue_date' => now(),
                'due_date' => $dueDate,
                'subtotal' => $totalAmount,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'currency' => $currentCompany->base_currency,
                'status' => 'draft',
                'notes' => $description,
                'created_by_user_id' => $currentUser->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Create invoice items
            foreach ($invoiceItems as $index => $item) {
                DB::table('acct.invoice_items')->insert([
                    'invoice_id' => $invoiceId,
                    'line_number' => $index + 1,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'total_price' => $item['total'],
                    'tax_rate' => 0,
                    'tax_amount' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::commit();

            $this->info('✅ Invoice created successfully!');
            $this->info("  Invoice Number: {$invoiceNumber}");
            $this->info("  Customer: {$customer}");
            $this->info('  Issue Date: '.now()->format('Y-m-d'));
            $this->info("  Due Date: {$dueDate}");
            $this->info("  Total Amount: {$currentCompany->base_currency} ".number_format($totalAmount, 2));
            $this->info('  Status: Draft');
            $this->info('  Items: '.count($invoiceItems));

            foreach ($invoiceItems as $item) {
                $this->info("    • {$item['description']} - {$item['quantity']} × {$item['unit_price']} = {$item['total']}");
            }

            return 0;
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Failed to create invoice: {$e->getMessage()}");

            return 1;
        }
    }
}
