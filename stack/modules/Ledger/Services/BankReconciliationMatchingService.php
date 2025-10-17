<?php

namespace Modules\Ledger\Services;

use App\Models\BankReconciliation;
use App\Models\BankReconciliationMatch;
use App\Models\BankStatementLine;
use App\Models\CreditNote;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Payment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankReconciliationMatchingService
{
    private array $matchingConfig = [
        'exact_amount_threshold' => 0.01,
        'date_tolerance_days' => 7,
        'description_similarity_threshold' => 0.7,
        'high_confidence_threshold' => 0.9,
        'medium_confidence_threshold' => 0.7,
        'low_confidence_threshold' => 0.5,
    ];

    public function runAutoMatch(BankReconciliation $reconciliation, array $options = []): Collection
    {
        $config = array_merge($this->matchingConfig, $options);

        // Get unmatched statement lines
        $unmatchedLines = $this->getUnmatchedStatementLines($reconciliation);

        $matches = collect();

        foreach ($unmatchedLines as $statementLine) {
            $candidates = $this->findMatchingCandidates($statementLine, $reconciliation, $config);

            if ($candidates->isNotEmpty()) {
                $bestMatch = $this->selectBestMatch($candidates, $config);

                if ($bestMatch && $bestMatch['confidence'] >= $config['low_confidence_threshold']) {
                    $match = $this->createMatch($statementLine, $bestMatch, $reconciliation, true);
                    $matches->push($match);
                }
            }
        }

        return $matches;
    }

    public function findMatchingCandidates(BankStatementLine $statementLine, BankReconciliation $reconciliation, array $config): Collection
    {
        $candidates = collect();

        // Search for payment matches
        $paymentCandidates = $this->findPaymentCandidates($statementLine, $reconciliation, $config);
        $candidates = $candidates->merge($paymentCandidates);

        // Search for invoice matches (for payments)
        $invoiceCandidates = $this->findInvoiceCandidates($statementLine, $reconciliation, $config);
        $candidates = $candidates->merge($invoiceCandidates);

        // Search for journal entry matches
        $journalCandidates = $this->findJournalEntryCandidates($statementLine, $reconciliation, $config);
        $candidates = $candidates->merge($journalCandidates);

        // Search for credit note matches (for credits)
        $creditNoteCandidates = $this->findCreditNoteCandidates($statementLine, $reconciliation, $config);
        $candidates = $candidates->merge($creditNoteCandidates);

        // Remove duplicates and sort by confidence
        return $candidates->unique(function ($item) {
            return $item['source_type'].':'.$item['source_id'];
        })->sortByDesc('confidence')->values();
    }

    private function findPaymentCandidates(BankStatementLine $statementLine, BankReconciliation $reconciliation, array $config): Collection
    {
        $query = Payment::where('company_id', $reconciliation->company_id)
            ->where('amount', abs($statementLine->amount));

        // Apply date tolerance filter
        if ($statementLine->transaction_date) {
            $dateStart = $statementLine->transaction_date->copy()->subDays($config['date_tolerance_days']);
            $dateEnd = $statementLine->transaction_date->copy()->addDays($config['date_tolerance_days']);
            $query->whereBetween('payment_date', [$dateStart, $dateEnd]);
        }

        $payments = $query->get();

        return $payments->map(function ($payment) use ($statementLine, $config) {
            return [
                'source_type' => 'acct.payment',
                'source_id' => $payment->id,
                'source' => $payment,
                'confidence' => $this->calculatePaymentConfidence($statementLine, $payment, $config),
            ];
        })->filter(function ($candidate) use ($config) {
            return $candidate['confidence'] >= $config['low_confidence_threshold'];
        });
    }

    private function findInvoiceCandidates(BankStatementLine $statementLine, BankReconciliation $reconciliation, array $config): Collection
    {
        // Only match positive amounts to invoices (incoming payments)
        if ($statementLine->amount <= 0) {
            return collect();
        }

        $invoices = Invoice::where('company_id', $reconciliation->company_id)
            ->where('total', $statementLine->amount)
            ->where('status', '!=', 'paid')
            ->where('status', '!=', 'void')
            ->get();

        return $invoices->map(function ($invoice) use ($statementLine, $config) {
            return [
                'source_type' => 'acct.invoice',
                'source_id' => $invoice->id,
                'source' => $invoice,
                'confidence' => $this->calculateInvoiceConfidence($statementLine, $invoice, $config),
            ];
        })->filter(function ($candidate) use ($config) {
            return $candidate['confidence'] >= $config['low_confidence_threshold'];
        });
    }

    private function findJournalEntryCandidates(BankStatementLine $statementLine, BankReconciliation $reconciliation, array $config): Collection
    {
        // Find journal entries with matching amounts
        $journalEntries = JournalEntry::where('company_id', $reconciliation->company_id)
            ->whereHas('transactions', function ($query) use ($statementLine) {
                $query->where('debit_amount', abs($statementLine->amount))
                    ->orWhere('credit_amount', abs($statementLine->amount));
            })
            ->where('journal_date', '>=', $statementLine->transaction_date->copy()->subDays($config['date_tolerance_days']))
            ->where('journal_date', '<=', $statementLine->transaction_date->copy()->addDays($config['date_tolerance_days']))
            ->get();

        return $journalEntries->map(function ($journalEntry) use ($statementLine, $config) {
            return [
                'source_type' => 'ledger.journal_entry',
                'source_id' => $journalEntry->id,
                'source' => $journalEntry,
                'confidence' => $this->calculateJournalEntryConfidence($statementLine, $journalEntry, $config),
            ];
        })->filter(function ($candidate) use ($config) {
            return $candidate['confidence'] >= $config['low_confidence_threshold'];
        });
    }

    private function findCreditNoteCandidates(BankStatementLine $statementLine, BankReconciliation $reconciliation, array $config): Collection
    {
        // Only match negative amounts to credit notes (refunds)
        if ($statementLine->amount >= 0) {
            return collect();
        }

        $creditNotes = CreditNote::where('company_id', $reconciliation->company_id)
            ->where('total', abs($statementLine->amount))
            ->where('status', '!=', 'void')
            ->get();

        return $creditNotes->map(function ($creditNote) use ($statementLine, $config) {
            return [
                'source_type' => 'acct.credit_note',
                'source_id' => $creditNote->id,
                'source' => $creditNote,
                'confidence' => $this->calculateCreditNoteConfidence($statementLine, $creditNote, $config),
            ];
        })->filter(function ($candidate) use ($config) {
            return $candidate['confidence'] >= $config['low_confidence_threshold'];
        });
    }

    private function calculatePaymentConfidence(BankStatementLine $statementLine, Payment $payment, array $config): float
    {
        $confidence = 0;

        // Amount matching (highest weight)
        $amountDiff = abs(abs($statementLine->amount) - abs($payment->amount));
        if ($amountDiff <= $config['exact_amount_threshold']) {
            $confidence += 0.5;
        } elseif ($amountDiff <= abs($payment->amount) * 0.05) { // Within 5%
            $confidence += 0.3;
        }

        // Date proximity
        if ($statementLine->transaction_date && $payment->payment_date) {
            $daysDiff = abs($statementLine->transaction_date->diffInDays($payment->payment_date));
            if ($daysDiff === 0) {
                $confidence += 0.3;
            } elseif ($daysDiff <= 3) {
                $confidence += 0.2;
            } elseif ($daysDiff <= $config['date_tolerance_days']) {
                $confidence += 0.1;
            }
        }

        // Reference matching
        if ($statementLine->reference_number && $payment->reference) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($statementLine->reference_number),
                strtolower($payment->reference)
            );
            if ($similarity >= 0.9) {
                $confidence += 0.2;
            } elseif ($similarity >= 0.7) {
                $confidence += 0.1;
            }
        }

        return min($confidence, 1.0);
    }

    private function calculateInvoiceConfidence(BankStatementLine $statementLine, Invoice $invoice, array $config): float
    {
        $confidence = 0;

        // Amount matching (already filtered, so perfect match)
        $confidence += 0.5;

        // Customer name matching in description
        if ($invoice->customer && $statementLine->description) {
            $customerName = strtolower($invoice->customer->name);
            $description = strtolower($statementLine->description);

            if (str_contains($description, $customerName)) {
                $confidence += 0.3;
            } elseif ($this->calculateStringSimilarity($customerName, $description) >= 0.7) {
                $confidence += 0.2;
            }
        }

        // Invoice number matching
        if ($invoice->invoice_number && $statementLine->description) {
            $description = strtolower($statementLine->description);
            $invoiceNumber = strtolower($invoice->invoice_number);

            if (str_contains($description, $invoiceNumber)) {
                $confidence += 0.2;
            }
        }

        // Date proximity to invoice date
        if ($statementLine->transaction_date && $invoice->invoice_date) {
            $daysDiff = abs($statementLine->transaction_date->diffInDays($invoice->invoice_date));
            if ($daysDiff <= 30) { // Within a month
                $confidence += 0.1;
            }
        }

        return min($confidence, 1.0);
    }

    private function calculateJournalEntryConfidence(BankStatementLine $statementLine, JournalEntry $journalEntry, array $config): float
    {
        $confidence = 0;

        // Amount matching
        $confidence += 0.4;

        // Date matching
        if ($statementLine->transaction_date && $journalEntry->journal_date) {
            $daysDiff = abs($statementLine->transaction_date->diffInDays($journalEntry->journal_date));
            if ($daysDiff === 0) {
                $confidence += 0.3;
            } elseif ($daysDiff <= $config['date_tolerance_days']) {
                $confidence += 0.2;
            }
        }

        // Description matching
        if ($journalEntry->description && $statementLine->description) {
            $similarity = $this->calculateStringSimilarity(
                strtolower($journalEntry->description),
                strtolower($statementLine->description)
            );
            if ($similarity >= 0.8) {
                $confidence += 0.3;
            } elseif ($similarity >= 0.6) {
                $confidence += 0.1;
            }
        }

        return min($confidence, 1.0);
    }

    private function calculateCreditNoteConfidence(BankStatementLine $statementLine, CreditNote $creditNote, array $config): float
    {
        // Similar to invoice confidence but for refunds
        $confidence = 0.4; // Amount matching

        // Customer name matching
        if ($creditNote->customer && $statementLine->description) {
            $customerName = strtolower($creditNote->customer->name);
            $description = strtolower($statementLine->description);

            if (str_contains($description, $customerName)) {
                $confidence += 0.3;
            }
        }

        // Credit note number matching
        if ($creditNote->credit_note_number && $statementLine->description) {
            $description = strtolower($statementLine->description);
            $creditNoteNumber = strtolower($creditNote->credit_note_number);

            if (str_contains($description, $creditNoteNumber)) {
                $confidence += 0.2;
            }
        }

        return min($confidence, 1.0);
    }

    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        similar_text($str1, $str2, $percent);

        return $percent / 100;
    }

    private function selectBestMatch(Collection $candidates, array $config): ?array
    {
        if ($candidates->isEmpty()) {
            return null;
        }

        // Sort by confidence and return the best one
        return $candidates->sortByDesc('confidence')->first();
    }

    private function createMatch(BankStatementLine $statementLine, array $candidate, BankReconciliation $reconciliation, bool $autoMatched): BankReconciliationMatch
    {
        return DB::transaction(function () use ($statementLine, $candidate, $reconciliation, $autoMatched) {
            // Remove any existing matches for this statement line
            BankReconciliationMatch::where('statement_line_id', $statementLine->id)->delete();

            return BankReconciliationMatch::createMatch([
                'reconciliation_id' => $reconciliation->id,
                'statement_line_id' => $statementLine->id,
                'source_type' => $candidate['source_type'],
                'source_id' => $candidate['source_id'],
                'amount' => $statementLine->amount,
                'auto_matched' => $autoMatched,
                'confidence_score' => $candidate['confidence'],
            ], auth()->user());
        });
    }

    private function getUnmatchedStatementLines(BankReconciliation $reconciliation): Collection
    {
        return BankStatementLine::where('statement_id', $reconciliation->statement_id)
            ->whereDoesntHave('reconciliationMatch')
            ->orderBy('transaction_date')
            ->get();
    }

    public function createManualMatch(BankStatementLine $statementLine, string $sourceType, string $sourceId, float $amount, User $user): BankReconciliationMatch
    {
        // Validate that the source exists and belongs to the company
        $this->validateSource($sourceType, $sourceId, $statementLine->company_id);

        return DB::transaction(function () use ($statementLine, $sourceType, $sourceId, $amount, $user) {
            // Remove any existing matches for this statement line
            BankReconciliationMatch::where('statement_line_id', $statementLine->id)->delete();

            return BankReconciliationMatch::createMatch([
                'reconciliation_id' => $statementLine->statement->reconciliation?->id,
                'statement_line_id' => $statementLine->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'amount' => $amount,
                'auto_matched' => false,
                'confidence_score' => null, // Manual matches don't have confidence scores
            ], $user);
        });
    }

    public function removeMatch(BankReconciliationMatch $match, User $user): bool
    {
        return $match->deleteMatch($user);
    }

    private function validateSource(string $sourceType, string $sourceId, string $companyId): void
    {
        $validSources = [
            'acct.payment' => Payment::class,
            'acct.invoice' => Invoice::class,
            'ledger.journal_entry' => JournalEntry::class,
            'acct.credit_note' => CreditNote::class,
        ];

        if (! isset($validSources[$sourceType])) {
            throw new \InvalidArgumentException("Invalid source type: {$sourceType}");
        }

        $modelClass = $validSources[$sourceType];
        $source = $modelClass::find($sourceId);

        if (! $source) {
            throw new \InvalidArgumentException("Source not found: {$sourceType} {$sourceId}");
        }

        if ($source->company_id !== $companyId) {
            throw new \InvalidArgumentException('Source does not belong to the same company');
        }
    }
}
