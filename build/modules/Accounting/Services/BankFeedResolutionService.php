<?php

namespace App\Modules\Accounting\Services;

use App\Modules\Accounting\Models\BankAccount;
use App\Modules\Accounting\Models\BankTransaction;
use App\Modules\Accounting\Models\BillPayment;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Exception;

class BankFeedResolutionService
{
    protected GlPostingService $postingService;

    public function __construct(GlPostingService $postingService)
    {
        $this->postingService = $postingService;
    }

    /**
     * Mode 1: MATCH
     * Links a bank transaction to an existing system payment (AR) or bill payment (AP).
     */
    public function resolveMatch(BankTransaction $bankTransaction, Payment|BillPayment $target): BankTransaction
    {
        if ($bankTransaction->is_reconciled) {
            throw new Exception("Transaction is already reconciled.");
        }

        if (abs($bankTransaction->amount - $target->amount) > 0.01) {
            throw new Exception("Amount mismatch: Bank {$bankTransaction->amount} vs Target {$target->amount}");
        }

        return DB::transaction(function () use ($bankTransaction, $target) {
            if ($target instanceof Payment) {
                $bankTransaction->matched_payment_id = $target->id;
            } else {
                $bankTransaction->matched_bill_payment_id = $target->id;
            }

            $bankTransaction->is_reconciled = true;
            $bankTransaction->reconciled_date = now();
            $bankTransaction->reconciled_by_user_id = \Illuminate\Support\Facades\Auth::id();
            
            // Link to the GL transaction already created by the Payment/BillPayment
            $bankTransaction->gl_transaction_id = $target->transaction_id;
            
            $bankTransaction->save();

            return $bankTransaction;
        });
    }

    /**
     * Mode 2: CREATE
     * Creates a new GL transaction (Spend Money / Receive Money) from the bank feed.
     * 
     * @param array $lines Format: [['account_id' => uuid, 'amount' => float, 'description' => string]]
     */
    public function resolveCreate(BankTransaction $bankTransaction, array $lines, string $taxCode = null): BankTransaction
    {
        if ($bankTransaction->is_reconciled) {
            throw new Exception("Transaction is already reconciled.");
        }

        // Validate sum matches bank amount (absolute values)
        $totalAllocated = array_reduce($lines, fn($sum, $line) => $sum + $line['amount'], 0);
        if (abs($totalAllocated - abs($bankTransaction->amount)) > 0.01) {
            throw new Exception("Allocation mismatch: Allocated {$totalAllocated} vs Bank " . abs($bankTransaction->amount));
        }

        return DB::transaction(function () use ($bankTransaction, $lines) {
            $bankAccount = $bankTransaction->bankAccount;
            
            // Post to GL
            $glTransaction = $this->postingService->postBankTransaction(
                [
                    'company_id' => $bankTransaction->company_id,
                    'date' => $bankTransaction->transaction_date,
                    'currency' => $bankAccount->currency, 
                    'amount' => $bankTransaction->amount, // Pass signed amount
                    'description' => $bankTransaction->description,
                    'bank_account_id' => $bankAccount->gl_account_id,
                    'reference_id' => $bankTransaction->id,
                ],
                $lines
            );

            $bankTransaction->gl_transaction_id = $glTransaction->id;
            $bankTransaction->is_reconciled = true;
            $bankTransaction->reconciled_date = now();
            $bankTransaction->reconciled_by_user_id = \Illuminate\Support\Facades\Auth::id();
            $bankTransaction->save();

            return $bankTransaction;
        });
    }

    /**
     * Mode 3: TRANSFER
     * Records a transfer between two bank accounts.
     */
    public function resolveTransfer(BankTransaction $bankTransaction, BankAccount $targetAccount): BankTransaction
    {
        if ($bankTransaction->is_reconciled) {
            throw new Exception("Transaction is already reconciled.");
        }
        
        $currentBankAccount = $bankTransaction->bankAccount;

        // Determine direction
        // If amount < 0 (Spend): Current -> Target
        // If amount > 0 (Receive): Target -> Current
        $isSpend = $bankTransaction->amount < 0;
        
        $fromAccount = $isSpend ? $currentBankAccount : $targetAccount;
        $toAccount = $isSpend ? $targetAccount : $currentBankAccount;

        return DB::transaction(function () use ($bankTransaction, $fromAccount, $toAccount) {
            $glTransaction = $this->postingService->postBankTransfer([
                'company_id' => $bankTransaction->company_id,
                'date' => $bankTransaction->transaction_date,
                'amount' => abs($bankTransaction->amount),
                'currency' => $fromAccount->currency,
                'from_account_id' => $fromAccount->gl_account_id,
                'to_account_id' => $toAccount->gl_account_id,
                'description' => $bankTransaction->description,
                'reference_id' => $bankTransaction->id,
            ]);
            
            // Update Bank Transaction
            $bankTransaction->gl_transaction_id = $glTransaction->id;
            $bankTransaction->is_reconciled = true;
            $bankTransaction->reconciled_date = now();
            $bankTransaction->save();

            return $bankTransaction;
        });
    }

    /**
     * Mode 4: PARK
     * Moves transaction to "Clarification Queue" by adding a note.
     */
    public function resolvePark(BankTransaction $bankTransaction, string $note): BankTransaction
    {
        $bankTransaction->notes = $note;
        // We leave is_reconciled = false.
        // The UI will filter these into a separate "Ask Accountant" list.
        $bankTransaction->save();
        
        return $bankTransaction;
    }
}
