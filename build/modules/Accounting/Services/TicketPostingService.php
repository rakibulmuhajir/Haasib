<?php

namespace App\Modules\Accounting\Services;

use App\Models\Company;
use App\Modules\Accounting\Models\Bill;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Transaction;
use App\Modules\Accounting\Models\VendorCredit;

/**
 * Ticket postings take every account from a template role. No ticket
 * posting reads income_account_id or expense_account_id, which is why
 * the account-type validators on invoice and bill lines stay exactly as
 * strict as they are -- the liability clearing account never goes near
 * a field called "income account".
 *
 * Amounts arrive already in base currency. Converting is the caller's
 * job, because only the caller knows the ticket's two exchange rates.
 */
final class TicketPostingService
{
    use ResolvesPostingTemplates;

    /*
     * PostingService installs the default templates before every posting, so a
     * company that has never posted a given document type gets one on first use
     * rather than an error. This service resolved templates without doing that,
     * which is why the first ticket booking on any company answered
     *
     *   No active default posting template for TICKET_INVOICE.
     *
     * even though the six accounts those templates map to had been installed on
     * every company by migration. Following the same pattern here means the four
     * ticket templates arrive the same way the other six always have.
     */
    public function __construct(
        private readonly PostingTemplateInstaller $templateInstaller,
    ) {}

    public function postTicketInvoice(Invoice $invoice, TicketSaleAmounts $amounts): Transaction
    {
        $invoice->loadMissing(['customer', 'company']);

        $company = $invoice->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Invoice company missing.');
        }

        $transactionDate = $invoice->invoice_date ?? now();
        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'TICKET_INVOICE', $transactionDate);
        $roleAccounts = $this->roleAccounts($template);

        foreach (['AR', 'CLEARING', 'REVENUE', 'DISCOUNT_GIVEN'] as $role) {
            if (empty($roleAccounts[$role])) {
                throw new \RuntimeException("Ticket invoice posting template is missing the {$role} role mapping.");
            }
        }

        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $arAmount = round((float) ($invoice->base_amount ?? $invoice->total_amount), 2);
        $discountAmount = round($amounts->discountBase, 2);
        $supplierCostAmount = round($amounts->supplierCostBase, 2);
        $commissionAmount = round($amounts->commissionBase, 2);
        $serviceFeeAmount = round($amounts->serviceFeeBase, 2);

        $entries = [];

        $entries[] = [
            'account_id' => $roleAccounts['AR'],
            'type' => 'debit',
            'amount' => $arAmount,
            'description' => 'Accounts Receivable',
        ];

        if ($discountAmount > 0) {
            $entries[] = [
                'account_id' => $roleAccounts['DISCOUNT_GIVEN'],
                'type' => 'debit',
                'amount' => $discountAmount,
                'description' => 'Discount given',
            ];
        }

        $entries[] = [
            'account_id' => $roleAccounts['CLEARING'],
            'type' => 'credit',
            'amount' => $supplierCostAmount,
            'description' => 'Ticket supplier clearing',
        ];

        $entries[] = [
            'account_id' => $roleAccounts['REVENUE'],
            'type' => 'credit',
            'amount' => $commissionAmount,
            'description' => 'Ticket commission revenue',
        ];

        if ($serviceFeeAmount > 0) {
            if (empty($roleAccounts['SERVICE_FEE'])) {
                throw new \RuntimeException('Ticket invoice posting template is missing the SERVICE_FEE role mapping.');
            }

            $entries[] = [
                'account_id' => $roleAccounts['SERVICE_FEE'],
                'type' => 'credit',
                'amount' => $serviceFeeAmount,
                'description' => 'Ticket service fee revenue',
            ];
        }

        $this->applyRoundingLeg($entries, $roleAccounts);
        $this->assertBalanced($entries);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $invoice->invoice_number,
            'transaction_type' => 'ticket_invoice',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $invoice->base_currency ?? $company->base_currency,
            'base_currency' => $invoice->base_currency ?? $company->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.invoices',
            'reference_id' => $invoice->id,
            'description' => "Ticket invoice {$invoice->invoice_number}",
        ], $entries);
    }

    public function postTicketBill(Bill $bill, float $supplierCostBase): Transaction
    {
        $bill->loadMissing(['vendor', 'company']);

        $company = $bill->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Bill company missing.');
        }

        $transactionDate = $bill->bill_date ?? now();
        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'TICKET_BILL', $transactionDate);
        $roleAccounts = $this->roleAccounts($template);

        foreach (['AP', 'CLEARING'] as $role) {
            if (empty($roleAccounts[$role])) {
                throw new \RuntimeException("Ticket bill posting template is missing the {$role} role mapping.");
            }
        }

        $period = $this->resolveOpenPeriod($company->id, $transactionDate);

        $amount = round($supplierCostBase, 2);

        $entries = [
            [
                'account_id' => $roleAccounts['CLEARING'],
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Ticket supplier clearing',
            ],
            [
                'account_id' => $roleAccounts['AP'],
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Accounts Payable',
            ],
        ];

        $this->assertBalanced($entries);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $bill->bill_number,
            'transaction_type' => 'ticket_bill',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $bill->base_currency ?? $company->base_currency,
            'base_currency' => $bill->base_currency ?? $company->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.bills',
            'reference_id' => $bill->id,
            'description' => "Ticket bill {$bill->bill_number}",
        ], $entries);
    }

    /**
     * A cancellation raises one credit note and one vendor credit, never
     * both zero. The buyer leg debits CANCELLATION_ADJUSTMENT and credits
     * AR; the supplier leg (below) does the opposite. Posted separately,
     * so the net balance left in the role account -- buyer return minus
     * supplier return -- is the cancellation cost, falling out of the
     * ledger rather than being computed beside it.
     */
    public function postTicketCreditNote(CreditNote $note, float $buyerReturnBase): Transaction
    {
        if ($buyerReturnBase <= 0.0) {
            throw new \RuntimeException('Buyer return must be greater than zero; a zero leg raises no document.');
        }

        $note->loadMissing(['customer', 'company']);

        $company = $note->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Credit note company missing.');
        }

        $transactionDate = $note->credit_date ?? now();
        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'TICKET_CREDIT_NOTE', $transactionDate);
        $roleAccounts = $this->roleAccounts($template);

        foreach (['AR', 'CANCELLATION_ADJUSTMENT'] as $role) {
            if (empty($roleAccounts[$role])) {
                throw new \RuntimeException("Ticket credit note posting template is missing the {$role} role mapping.");
            }
        }

        $period = $this->resolveOpenPeriod($company->id, $transactionDate);
        $amount = round($buyerReturnBase, 2);

        $entries = [
            [
                'account_id' => $roleAccounts['CANCELLATION_ADJUSTMENT'],
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Ticket cancellation adjustment',
            ],
            [
                'account_id' => $roleAccounts['AR'],
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Accounts Receivable',
            ],
        ];

        $this->assertBalanced($entries);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $note->credit_note_number,
            'transaction_type' => 'ticket_credit_note',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $note->base_currency ?? $company->base_currency,
            'base_currency' => $note->base_currency ?? $company->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.credit_notes',
            'reference_id' => $note->id,
            'description' => "Ticket credit note {$note->credit_note_number}",
        ], $entries);
    }

    public function postTicketVendorCredit(VendorCredit $credit, float $supplierReturnBase): Transaction
    {
        if ($supplierReturnBase <= 0.0) {
            throw new \RuntimeException('Supplier return must be greater than zero; a zero leg raises no document.');
        }

        $credit->loadMissing(['vendor', 'company']);

        $company = $credit->company;
        if (! $company instanceof Company) {
            throw new \RuntimeException('Vendor credit company missing.');
        }

        $transactionDate = $credit->credit_date ?? now();
        $this->templateInstaller->ensureDefaults($company);
        $template = $this->resolveTemplate($company->id, 'TICKET_VENDOR_CREDIT', $transactionDate);
        $roleAccounts = $this->roleAccounts($template);

        foreach (['AP', 'CANCELLATION_ADJUSTMENT'] as $role) {
            if (empty($roleAccounts[$role])) {
                throw new \RuntimeException("Ticket vendor credit posting template is missing the {$role} role mapping.");
            }
        }

        $period = $this->resolveOpenPeriod($company->id, $transactionDate);
        $amount = round($supplierReturnBase, 2);

        $entries = [
            [
                'account_id' => $roleAccounts['AP'],
                'type' => 'debit',
                'amount' => $amount,
                'description' => 'Accounts Payable',
            ],
            [
                'account_id' => $roleAccounts['CANCELLATION_ADJUSTMENT'],
                'type' => 'credit',
                'amount' => $amount,
                'description' => 'Ticket cancellation adjustment',
            ],
        ];

        $this->assertBalanced($entries);

        return $this->createTransaction([
            'company_id' => $company->id,
            'transaction_number' => $credit->credit_number,
            'transaction_type' => 'ticket_vendor_credit',
            'transaction_date' => $transactionDate,
            'posting_date' => $transactionDate,
            'fiscal_year_id' => $period->fiscal_year_id,
            'period_id' => $period->id,
            'currency' => $credit->base_currency ?? $company->base_currency,
            'base_currency' => $credit->base_currency ?? $company->base_currency,
            'exchange_rate' => null,
            'reference_type' => 'acct.vendor_credits',
            'reference_id' => $credit->id,
            'description' => "Ticket vendor credit {$credit->credit_number}",
        ], $entries);
    }

    /**
     * If debits and credits differ by no more than 0.01, add a single
     * balancing entry to the ROUNDING account. A larger difference is a
     * defect in the caller's amounts, not a rounding difference, and must
     * throw rather than be absorbed.
     *
     * @param array<int, array{account_id:string,type:'debit'|'credit',amount:float,description?:string}> $entries
     */
    private function applyRoundingLeg(array &$entries, array $roleAccounts): void
    {
        $debit = 0.0;
        $credit = 0.0;
        foreach ($entries as $entry) {
            if ($entry['type'] === 'debit') {
                $debit += (float) $entry['amount'];
            } else {
                $credit += (float) $entry['amount'];
            }
        }

        $diff = round($debit - $credit, 2);
        if ($diff === 0.0) {
            return;
        }

        if (abs($diff) > 0.01) {
            throw new \RuntimeException('Ticket invoice amounts do not balance; check the ticket rows feeding this posting.');
        }

        if (empty($roleAccounts['ROUNDING'])) {
            throw new \RuntimeException('Ticket invoice posting template is missing the ROUNDING role mapping.');
        }

        // Debits exceed credits: the rounding entry goes on the credit side, and vice versa.
        $entries[] = [
            'account_id' => $roleAccounts['ROUNDING'],
            'type' => $diff > 0 ? 'credit' : 'debit',
            'amount' => abs($diff),
            'description' => 'Rounding',
        ];
    }
}
