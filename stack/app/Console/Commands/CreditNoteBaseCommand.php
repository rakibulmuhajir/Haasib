<?php

namespace App\Console\Commands;

use App\Services\ContextService;
use App\Services\CreditNoteService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

abstract class CreditNoteBaseCommand extends Command
{
    protected CreditNoteService $creditNoteService;

    protected ContextService $contextService;

    protected $company;

    /**
     * Execute the command logic.
     */
    protected function executeCommand(): int
    {
        try {
            $this->initializeServices();
            $this->setupCompanyContext();

            return $this->handleCommand();
        } catch (ValidationException $exception) {
            $this->error('Validation failed:');
            foreach ($exception->errors() as $field => $errors) {
                foreach ($errors as $error) {
                    $this->line("  {$field}: {$error}");
                }
            }

            return self::FAILURE;
        } catch (\Throwable $exception) {
            $this->handleServiceException($exception);

            return self::FAILURE;
        }
    }

    /**
     * Initialize required services.
     */
    protected function initializeServices(): void
    {
        $this->contextService = app(ContextService::class);
        $this->creditNoteService = app(CreditNoteService::class);
    }

    /**
     * Setup company context.
     */
    protected function setupCompanyContext(): void
    {
        $input = $this->parseInput();
        $companyId = $input['company'] ?? null;

        if ($companyId) {
            $this->company = $this->contextService->getCompanyById($companyId);
        } else {
            $this->company = $this->contextService->getCurrentCompany();
        }

        if (! $this->company) {
            $this->error('No company context available. Use --company=<id> to specify a company.');
            exit(1);
        }
    }

    /**
     * Parse command input including arguments and options.
     */
    protected function parseInput(): array
    {
        $input = [];

        // Parse arguments
        $arguments = $this->arguments();
        unset($arguments['command']);
        $input = array_merge($input, $arguments);

        // Parse options
        $options = $this->options();
        $input = array_merge($input, $options);

        return $input;
    }

    /**
     * Handle service exceptions with user-friendly messages.
     */
    protected function handleServiceException(\Throwable $exception): void
    {
        $message = match (get_class($exception)) {
            'Illuminate\Database\Eloquent\ModelNotFoundException' => 'Record not found.',
            'Illuminate\Auth\Access\AuthorizationException' => 'You do not have permission to perform this action.',
            'Illuminate\Validation\ValidationException' => $exception->getMessage(),
            default => 'An error occurred: '.$exception->getMessage(),
        };

        $this->error($message);

        if (config('app.debug')) {
            $this->line('Exception: '.get_class($exception));
            $this->line('File: '.$exception->getFile().':'.$exception->getLine());
        }
    }

    /**
     * Log command execution for audit trail.
     */
    protected function logExecution(string $action, array $context = []): void
    {
        activity()
            ->causedBy(auth()->user())
            ->withProperties(array_merge([
                'command' => static::class,
                'action' => $action,
                'company_id' => $this->company->id,
            ], $context))
            ->log("CLI: {$action}");
    }

    /**
     * Find a credit note by identifier (ID, number, or partial match).
     */
    protected function findCreditNote(string $identifier): \App\Models\CreditNote
    {
        try {
            return $this->creditNoteService->findCreditNoteByIdentifier($identifier, $this->company);
        } catch (\Exception $e) {
            $this->error("Credit note '{$identifier}' not found.");
            exit(1);
        }
    }

    /**
     * Display credit note summary.
     */
    protected function displayCreditNoteSummary(\App\Models\CreditNote $creditNote): void
    {
        $this->info($creditNote->credit_note_number);
        $this->line(str_repeat('=', strlen($creditNote->credit_note_number)));
        $this->line("Invoice: {$creditNote->invoice->invoice_number}");
        $this->line("Customer: {$creditNote->invoice->customer->name}");
        $this->line("Reason: {$creditNote->reason}");
        $this->line('Amount: ${'.number_format($creditNote->amount, 2).'}');
        $this->line('Tax: ${'.number_format($creditNote->tax_amount, 2).'}');
        $this->line('Total: ${'.number_format($creditNote->total_amount, 2).'}');
        $this->line('Status: '.ucfirst($creditNote->status));
        $this->line('Created: '.$creditNote->created_at->format('Y-m-d H:i:s'));

        if ($creditNote->posted_at) {
            $this->line('Posted: '.$creditNote->posted_at->format('Y-m-d H:i:s'));
        }

        if ($creditNote->cancelled_at) {
            $this->line('Cancelled: '.$creditNote->cancelled_at->format('Y-m-d H:i:s'));
            $this->line("Cancellation Reason: {$creditNote->cancellation_reason}");
        }
    }

    /**
     * Display credit note items.
     */
    protected function displayCreditNoteItems(\App\Models\CreditNote $creditNote): void
    {
        $items = $creditNote->items;

        if ($items->isEmpty()) {
            $this->line('No items found.');

            return;
        }

        $this->line('');
        $this->info('Credit Note Items:');
        $this->line(str_repeat('-', 80));

        $tableData = [];
        $subtotal = 0;
        $totalTax = 0;
        $grandTotal = 0;

        foreach ($items as $index => $item) {
            $itemSubtotal = $item->subtotal;
            $itemTax = $item->tax_amount;
            $itemTotal = $item->total_amount;

            $subtotal += $itemSubtotal;
            $totalTax += $itemTax;
            $grandTotal += $itemTotal;

            $tableData[] = [
                '#' => $index + 1,
                'Description' => substr($item->description, 0, 40),
                'Qty' => $item->quantity,
                'Price' => '$'.number_format($item->unit_price, 2),
                'Tax' => $item->tax_rate.'%',
                'Total' => '$'.number_format($itemTotal, 2),
            ];
        }

        $this->table(['#', 'Description', 'Qty', 'Price', 'Tax', 'Total'], $tableData);

        $this->line(str_repeat('-', 80));
        $this->line('Subtotal: ${'.number_format($subtotal, 2).'}');
        $this->line('Tax: ${'.number_format($totalTax, 2).'}');
        $this->line('Total: ${'.number_format($grandTotal, 2).'}');
    }

    /**
     * Parse natural language input for credit note creation.
     */
    protected function parseNaturalLanguageInput(string $input): array
    {
        // Simple natural language parsing for credit notes
        $result = [
            'reason' => null,
            'amount' => null,
            'invoice_identifier' => null,
            'notes' => null,
        ];

        // Extract invoice identifier
        if (preg_match('/invoice\s+(INV-\d+|\d+)/i', $input, $matches)) {
            $result['invoice_identifier'] = $matches[1];
        }

        // Extract amount
        if (preg_match('/\$?(\d+(?:\.\d{2})?)/', $input, $matches)) {
            $result['amount'] = (float) $matches[1];
        }

        // Extract reason (simple heuristic)
        $reasons = [
            'returned goods', 'product return', 'refund', 'discount', 'price adjustment',
            'billing error', 'service credit', 'correction', 'customer credit',
        ];

        foreach ($reasons as $reason) {
            if (stripos($input, $reason) !== false) {
                $result['reason'] = ucwords($reason);
                break;
            }
        }

        // Default reason if none found
        if (! $result['reason']) {
            $result['reason'] = 'Credit adjustment';
        }

        return $result;
    }

    /**
     * Ask for confirmation before proceeding with critical operations.
     */
    protected function confirmAction(string $action, string $target = ''): bool
    {
        $message = "Are you sure you want to {$action}".($target ? " {$target}?" : '?');

        return $this->confirm($message);
    }

    /**
     * Abstract method to be implemented by concrete commands.
     */
    abstract protected function handleCommand(): int;
}
