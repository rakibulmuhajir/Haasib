<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CreditNote;
use App\Models\CreditNoteItem;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreditNoteService
{
    public function __construct(
        private readonly ContextService $contextService,
        private readonly AuthService $authService,
        private readonly CreditNotePdfService $pdfService,
        private readonly CreditNoteEmailService $emailService
    ) {}

    /**
     * Get credit notes for a company with filtering and pagination.
     */
    public function getCreditNotesForCompany(
        Company $company,
        User $user,
        array $filters = [],
        int $perPage = 50,
        int $page = 1
    ): LengthAwarePaginator {
        $this->authService->canAccessCompany($user, $company);

        $query = CreditNote::forCompany($company->id)
            ->with(['invoice.customer', 'creator']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->withStatus($filters['status']);
        }

        if (isset($filters['invoice_id'])) {
            $query->where('invoice_id', $filters['invoice_id']);
        }

        if (isset($filters['customer_id'])) {
            $query->whereHas('invoice.customer', function ($q) use ($filters) {
                $q->where('id', $filters['customer_id']);
            });
        }

        if (isset($filters['currency'])) {
            $query->where('currency', strtoupper($filters['currency']));
        }

        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (isset($filters['amount_from'])) {
            $query->where('total_amount', '>=', $filters['amount_from']);
        }

        if (isset($filters['amount_to'])) {
            $query->where('total_amount', '<=', $filters['amount_to']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('credit_note_number', 'ilike', "%{$search}%")
                    ->orWhere('reason', 'ilike', "%{$search}%")
                    ->orWhereHas('invoice', function ($subQ) use ($search) {
                        $subQ->where('invoice_number', 'ilike', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Create a new credit note.
     */
    public function createCreditNote(
        Company $company,
        array $data,
        User $user
    ): CreditNote {
        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.create');

        // Validate input data
        $this->validateCreditNoteData($data, $company);

        // Find and validate the invoice
        $invoice = Invoice::findOrFail($data['invoice_id']);
        $this->validateInvoiceForCreditNote($invoice, $company, $data);

        // Validate credit amount against invoice balance and existing credit notes
        $this->validateCreditAmount($data['total_amount'], $invoice, $data['invoice_id'] ?? null);

        DB::beginTransaction();

        try {
            // Generate credit note number inside the transaction to avoid race conditions
            $creditNoteNumber = CreditNote::generateCreditNoteNumber($company->id);

            // Create credit note
            $creditNote = CreditNote::create([
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'credit_note_number' => $creditNoteNumber,
                'reason' => $data['reason'],
                'amount' => $data['amount'],
                'tax_amount' => $data['tax_amount'] ?? 0,
                'total_amount' => $data['total_amount'],
                'currency' => $data['currency'] ?? $invoice->currency,
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'terms' => $data['terms'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            // Create credit note items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                $this->createCreditNoteItems($creditNote, $data['items']);
            }

            // Log the action
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'company_id' => $company->id,
                    'invoice_id' => $invoice->id,
                    'action' => 'credit_note_created',
                    'amount' => $creditNote->total_amount,
                ])
                ->log('Credit note created');

            DB::commit();

            return $creditNote->load(['invoice.customer', 'items']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Update an existing credit note.
     */
    public function updateCreditNote(
        CreditNote $creditNote,
        array $data,
        User $user
    ): CreditNote {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.update');

        if ($creditNote->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft credit notes can be updated']);
        }

        // Validate input data
        $this->validateUpdateData($data, $creditNote);

        DB::beginTransaction();

        try {
            $creditNote->update($data);

            // Update items if provided
            if (isset($data['items']) && is_array($data['items'])) {
                // Delete existing items
                $creditNote->items()->delete();
                // Create new items
                $this->createCreditNoteItems($creditNote, $data['items']);
            }

            // Log the action
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'credit_note_updated',
                    'changes' => $data,
                ])
                ->log('Credit note updated');

            DB::commit();

            return $creditNote->load(['invoice.customer', 'items']);
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Post a credit note to the ledger.
     */
    public function postCreditNote(
        CreditNote $creditNote,
        User $user,
        bool $autoApply = false
    ): CreditNote {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.post');

        if (! $creditNote->canBePosted()) {
            throw ValidationException::withMessages(['status' => 'Credit note cannot be posted']);
        }

        // Validate before posting
        $errors = $creditNote->validateForPosting();
        if (! empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        DB::beginTransaction();

        try {
            $creditNote->post();

            // Create ledger entries (integration point with existing ledger system)
            $this->createLedgerEntries($creditNote, $user);

            // Auto-apply to invoice if requested and possible
            $autoApplied = false;
            if ($autoApply) {
                $autoApplied = $this->autoApplyCreditNote($creditNote, $user);
            }

            // Log the action
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'credit_note_posted',
                    'amount' => $creditNote->total_amount,
                    'auto_applied' => $autoApplied,
                ])
                ->log('Credit note posted to ledger'.($autoApplied ? ' and auto-applied' : ''));

            DB::commit();

            return $creditNote->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Cancel a credit note.
     */
    public function cancelCreditNote(
        CreditNote $creditNote,
        string $reason,
        User $user
    ): CreditNote {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.cancel');

        if (! $creditNote->canBeCancelled()) {
            throw ValidationException::withMessages(['status' => 'Credit note cannot be cancelled']);
        }

        DB::beginTransaction();

        try {
            $creditNote->cancel($reason);

            // Create reversal ledger entries if posted
            if ($creditNote->isPosted()) {
                $this->createReversalLedgerEntries($creditNote, $user);
            }

            // Log the action
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'credit_note_cancelled',
                    'reason' => $reason,
                ])
                ->log('Credit note cancelled');

            DB::commit();

            return $creditNote->fresh();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Apply a credit note to its invoice balance.
     */
    public function applyCreditNoteToInvoice(
        CreditNote $creditNote,
        User $user,
        ?string $notes = null
    ): bool {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.apply');

        if (! $creditNote->isPosted()) {
            throw ValidationException::withMessages(['status' => 'Only posted credit notes can be applied']);
        }

        return $creditNote->applyToInvoice($user, $notes);
    }

    /**
     * Get credit note statistics for a company.
     */
    public function getCreditNoteStatistics(
        Company $company,
        User $user
    ): array {
        $this->authService->canAccessCompany($user, $company);

        return [
            'total_credit_notes' => CreditNote::forCompany($company->id)->count(),
            'draft_credit_notes' => CreditNote::forCompany($company->id)->draft()->count(),
            'posted_credit_notes' => CreditNote::forCompany($company->id)->posted()->count(),
            'cancelled_credit_notes' => CreditNote::forCompany($company->id)->cancelled()->count(),
            'total_amount_issued' => CreditNote::forCompany($company->id)->sum('total_amount'),
            'total_amount_applied' => DB::table('acct.credit_note_applications')
                ->join('acct.credit_notes', 'credit_note_applications.credit_note_id', '=', 'credit_notes.id')
                ->where('credit_notes.company_id', $company->id)
                ->sum('credit_note_applications.amount_applied'),
            'credit_notes_by_currency' => CreditNote::forCompany($company->id)
                ->selectRaw('currency, count(*) as count, sum(total_amount) as total')
                ->groupBy('currency')
                ->get()
                ->keyBy('currency'),
            'recently_created' => CreditNote::forCompany($company->id)
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(['id', 'credit_note_number', 'total_amount', 'status', 'created_at']),
        ];
    }

    /**
     * Find a credit note by ID, number, or partial match.
     */
    public function findCreditNoteByIdentifier(
        string $identifier,
        Company $company
    ): CreditNote {
        $query = CreditNote::forCompany($company->id);

        // Try by UUID
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
            $creditNote = $query->where('id', $identifier)->first();
            if ($creditNote) {
                return $creditNote;
            }
        }

        // Try by exact credit note number
        $creditNote = $query->where('credit_note_number', $identifier)->first();
        if ($creditNote) {
            return $creditNote;
        }

        // Try by partial credit note number
        $creditNote = $query->where('credit_note_number', 'ilike', "%{$identifier}%")->first();
        if ($creditNote) {
            return $creditNote;
        }

        throw ValidationException::withMessages([
            'identifier' => 'Credit note not found',
        ]);
    }

    /**
     * Delete a credit note.
     */
    public function deleteCreditNote(
        CreditNote $creditNote,
        User $user
    ): void {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.delete');

        if ($creditNote->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'Only draft credit notes can be deleted']);
        }

        DB::beginTransaction();

        try {
            // Delete related items
            $creditNote->items()->delete();

            // Delete the credit note
            $creditNote->delete();

            // Log the action
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'credit_note_deleted',
                ])
                ->log('Credit note deleted');

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Validate credit note creation data.
     */
    private function validateCreditNoteData(array $data, Company $company): void
    {
        $validator = validator($data, [
            'invoice_id' => 'required|uuid|exists:pgsql.acct.invoices,id',
            'reason' => 'required|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'tax_amount' => 'nullable|numeric|min:0',
            'total_amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|size:3',
            'notes' => 'nullable|string|max:2000',
            'terms' => 'nullable|string|max:2000',
            'items' => 'nullable|array|min:1',
            'items.*.description' => 'required|string|max:500',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'items.*.discount_amount' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Validate credit note update data.
     */
    private function validateUpdateData(array $data, CreditNote $creditNote): void
    {
        $rules = [
            'reason' => 'sometimes|string|max:500',
            'notes' => 'sometimes|string|max:2000',
            'terms' => 'sometimes|string|max:2000',
        ];

        // Allow amount/tax changes only for draft credit notes
        if ($creditNote->status === 'draft') {
            $rules = array_merge($rules, [
                'amount' => 'sometimes|numeric|min:0.01',
                'tax_amount' => 'sometimes|numeric|min:0',
                'total_amount' => 'sometimes|numeric|min:0.01',
                'items' => 'sometimes|array|min:1',
                'items.*.description' => 'required|string|max:500',
                'items.*.quantity' => 'required|numeric|min:0.01',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.tax_rate' => 'nullable|numeric|min:0|max:100',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
            ]);
        }

        $validator = validator($data, $rules);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * Create credit note items.
     */
    private function createCreditNoteItems(CreditNote $creditNote, array $items): void
    {
        foreach ($items as $item) {
            CreditNoteItem::create([
                'credit_note_id' => $creditNote->id,
                'description' => $item['description'],
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'tax_rate' => $item['tax_rate'] ?? 0,
                'discount_amount' => $item['discount_amount'] ?? 0,
                'total_amount' => $this->calculateItemTotal($item),
            ]);
        }
    }

    /**
     * Calculate the total amount for a credit note item.
     */
    private function calculateItemTotal(array $item): float
    {
        $subtotal = $item['quantity'] * $item['unit_price'];
        $discountAmount = $item['discount_amount'] ?? 0;
        $discountedSubtotal = $subtotal - $discountAmount;
        $taxAmount = $discountedSubtotal * (($item['tax_rate'] ?? 0) / 100);

        return $discountedSubtotal + $taxAmount;
    }

    /**
     * Create ledger entries for a posted credit note.
     */
    private function createLedgerEntries(CreditNote $creditNote, User $user): void
    {
        try {
            // Create journal entry for credit note
            $journalEntry = $this->createCreditNoteJournalEntry($creditNote, $user);

            // Store journal entry reference on credit note
            $creditNote->journal_entry_id = $journalEntry->id;
            $creditNote->save();

            // Log the ledger integration
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'ledger_integration',
                    'journal_entry_id' => $journalEntry->id,
                    'amount' => $creditNote->total_amount,
                ])
                ->log('Credit note integrated with ledger');

        } catch (\Throwable $e) {
            // Log error but don't fail the credit note posting
            \Log::error('Failed to create ledger entries for credit note', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'error' => $e->getMessage(),
            ]);

            // Optionally create a pending ledger entry record
            $this->createPendingLedgerEntry($creditNote, $user, $e->getMessage());
        }
    }

    /**
     * Create reversal ledger entries for a cancelled credit note.
     */
    private function createReversalLedgerEntries(CreditNote $creditNote, User $user): void
    {
        if (! $creditNote->journal_entry_id) {
            // No ledger entry to reverse
            return;
        }

        try {
            $reversalEntry = $this->createCreditNoteReversalEntry($creditNote, $user);

            // Log the reversal
            activity()
                ->performedOn($creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'ledger_reversal',
                    'original_journal_entry_id' => $creditNote->journal_entry_id,
                    'reversal_journal_entry_id' => $reversalEntry->id,
                    'amount' => $creditNote->total_amount,
                ])
                ->log('Credit note ledger entries reversed');

        } catch (\Throwable $e) {
            \Log::error('Failed to create reversal ledger entries for credit note', [
                'credit_note_id' => $creditNote->id,
                'credit_note_number' => $creditNote->credit_note_number,
                'original_journal_entry_id' => $creditNote->journal_entry_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Validate invoice for credit note creation.
     */
    private function validateInvoiceForCreditNote(Invoice $invoice, Company $company, array $data): void
    {
        if ($invoice->company_id !== $company->id) {
            throw ValidationException::withMessages(['invoice_id' => 'Invoice does not belong to this company']);
        }

        if ($invoice->status !== 'posted') {
            throw ValidationException::withMessages(['invoice_id' => 'Credit notes can only be created for posted invoices']);
        }

        if ($invoice->is_deleted) {
            throw ValidationException::withMessages(['invoice_id' => 'Cannot create credit note for deleted invoice']);
        }

        if ($invoice->balance_due <= 0) {
            throw ValidationException::withMessages(['invoice_id' => 'Cannot create credit note for fully paid invoice']);
        }

        // Check currency compatibility
        $invoiceCurrency = $invoice->currency;
        $creditNoteCurrency = $data['currency'] ?? $invoiceCurrency;
        if ($invoiceCurrency !== $creditNoteCurrency) {
            throw ValidationException::withMessages([
                'currency' => "Credit note currency ({$creditNoteCurrency}) must match invoice currency ({$invoiceCurrency})",
            ]);
        }
    }

    /**
     * Validate credit amount against invoice balance and existing credit notes.
     */
    private function validateCreditAmount(float $amount, Invoice $invoice, ?string $excludeCreditNoteId = null): void
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Credit amount must be greater than zero']);
        }

        $availableBalance = $invoice->balance_due;

        // Calculate total amount from existing credit notes for this invoice
        $existingCreditAmount = CreditNote::where('invoice_id', $invoice->id)
            ->where('status', '!=', 'cancelled')
            ->when($excludeCreditNoteId, function ($query, $excludeId) {
                return $query->where('id', '!=', $excludeId);
            })
            ->sum('total_amount');

        $totalCreditWithNew = $existingCreditAmount + $amount;

        if ($totalCreditWithNew > $invoice->total_amount) {
            throw ValidationException::withMessages([
                'amount' => "Total credit notes ({$totalCreditWithNew}) cannot exceed invoice total ({$invoice->total_amount})",
            ]);
        }

        if ($amount > $availableBalance) {
            throw ValidationException::withMessages([
                'amount' => "Credit amount ({$amount}) cannot exceed available invoice balance ({$availableBalance})",
            ]);
        }
    }

    /**
     * Calculate credit limits for an invoice.
     */
    public function calculateCreditLimits(Invoice $invoice): array
    {
        $totalCreditNotes = CreditNote::where('invoice_id', $invoice->id)
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount');

        $appliedCreditNotes = DB::table('acct.credit_note_applications')
            ->join('acct.credit_notes', 'credit_note_applications.credit_note_id', '=', 'credit_notes.id')
            ->where('credit_notes.invoice_id', $invoice->id)
            ->where('credit_notes.status', '!=', 'cancelled')
            ->sum('credit_note_applications.amount_applied');

        $availableForCredit = min($invoice->balance_due, $invoice->total_amount - $totalCreditNotes);
        $remainingCreditCapacity = $invoice->total_amount - $totalCreditNotes;

        return [
            'invoice_total' => $invoice->total_amount,
            'invoice_balance_due' => $invoice->balance_due,
            'total_credit_notes_issued' => $totalCreditNotes,
            'total_credit_applied' => $appliedCreditNotes,
            'available_for_credit' => $availableForCredit,
            'remaining_credit_capacity' => $remainingCreditCapacity,
            'can_issue_credit' => $availableForCredit > 0,
        ];
    }

    /**
     * Create credit note with enhanced validation and business logic.
     */
    public function createCreditNoteAgainstInvoice(
        Invoice $invoice,
        float $amount,
        string $reason,
        User $user,
        array $options = []
    ): CreditNote {
        $this->authService->canAccessCompany($user, $invoice->company);
        $this->authService->hasPermission($user, 'credit_notes.create');

        // Validate invoice
        $this->validateInvoiceForCreditNote($invoice, $invoice->company, array_merge($options, [
            'total_amount' => $amount,
            'currency' => $options['currency'] ?? $invoice->currency,
        ]));

        // Validate amount
        $this->validateCreditAmount($amount, $invoice);

        // Check credit limits
        $creditLimits = $this->calculateCreditLimits($invoice);
        if (! $creditLimits['can_issue_credit']) {
            throw ValidationException::withMessages([
                'amount' => 'Invoice has reached its credit limit or has no available balance',
            ]);
        }

        // Prepare credit note data
        $creditNoteData = [
            'invoice_id' => $invoice->id,
            'amount' => $amount,
            'tax_amount' => $options['tax_amount'] ?? 0,
            'total_amount' => $amount,
            'reason' => $reason,
            'currency' => $options['currency'] ?? $invoice->currency,
            'notes' => $options['notes'] ?? null,
            'terms' => $options['terms'] ?? null,
            'items' => $options['items'] ?? null,
        ];

        return $this->createCreditNote($invoice->company, $creditNoteData, $user);
    }

    /**
     * Automatically apply credit note to invoice if possible.
     */
    public function autoApplyCreditNote(CreditNote $creditNote, User $user): bool
    {
        if (! $creditNote->isPosted()) {
            return false;
        }

        if ($creditNote->remainingBalance() <= 0) {
            return false;
        }

        if ($creditNote->invoice->balance_due <= 0) {
            return false; // Invoice already fully paid
        }

        return $this->applyCreditNoteToInvoice($creditNote, $user, 'Automatically applied on posting');
    }

    /**
     * Process automatic balance adjustments for multiple credit notes.
     */
    public function processAutomaticBalanceAdjustments(Company $company, User $user): array
    {
        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.apply');

        $results = [];

        // Find posted credit notes with remaining balance
        $postedCreditNotes = CreditNote::forCompany($company->id)
            ->posted()
            ->whereHas('invoice', function ($query) {
                $query->where('balance_due', '>', 0);
            })
            ->with(['invoice'])
            ->get();

        foreach ($postedCreditNotes as $creditNote) {
            if ($creditNote->remainingBalance() > 0 && $creditNote->invoice->balance_due > 0) {
                $applied = $this->autoApplyCreditNote($creditNote, $user);

                $results[] = [
                    'credit_note_id' => $creditNote->id,
                    'credit_note_number' => $creditNote->credit_note_number,
                    'invoice_number' => $creditNote->invoice->invoice_number,
                    'amount_applied' => min($creditNote->remainingBalance(), $creditNote->invoice->balance_due),
                    'applied' => $applied,
                ];
            }
        }

        return $results;
    }

    /**
     * Calculate the impact of credit note applications on customer balances.
     */
    public function calculateCustomerBalanceImpact(Company $company, ?Customer $customer = null): array
    {
        $query = CreditNote::forCompany($company->id)
            ->posted()
            ->with(['invoice.customer', 'applications']);

        if ($customer) {
            $query->whereHas('invoice.customer', function ($q) use ($customer) {
                $q->where('id', $customer->id);
            });
        }

        $creditNotes = $query->get();

        $impact = [
            'total_credit_notes' => $creditNotes->count(),
            'total_credit_amount' => $creditNotes->sum('total_amount'),
            'total_applied_amount' => $creditNotes->sum(function (CreditNote $creditNote) {
                return $creditNote->total_amount - $creditNote->remainingBalance();
            }),
            'total_remaining_balance' => $creditNotes->sum(function (CreditNote $creditNote) {
                return $creditNote->remainingBalance();
            }),
            'by_customer' => [],
        ];

        // Group by customer
        $byCustomer = $creditNotes->groupBy('invoice.customer.id');
        foreach ($byCustomer as $customerId => $customerCreditNotes) {
            $customerData = $customerCreditNotes->first()->invoice->customer;

            $impact['by_customer'][$customerId] = [
                'customer_name' => $customerData->name,
                'credit_notes_count' => $customerCreditNotes->count(),
                'total_credit_amount' => $customerCreditNotes->sum('total_amount'),
                'total_applied_amount' => $customerCreditNotes->sum(function (CreditNote $creditNote) {
                    return $creditNote->total_amount - $creditNote->remainingBalance();
                }),
                'remaining_balance' => $customerCreditNotes->sum(function (CreditNote $creditNote) {
                    return $creditNote->remainingBalance();
                }),
            ];
        }

        return $impact;
    }

    /**
     * Update invoice payment status based on credit note applications.
     */
    public function updateInvoicePaymentStatus(Invoice $invoice): void
    {
        $originalStatus = $invoice->payment_status;
        $originalPaidAt = $invoice->paid_at;

        if ($invoice->balance_due <= 0) {
            $invoice->payment_status = 'paid';
            $invoice->paid_at = $invoice->paid_at ?? now();
        } elseif ($invoice->balance_due < $invoice->total_amount) {
            $invoice->payment_status = 'partially_paid';
        } else {
            $invoice->payment_status = 'unpaid';
        }

        // Only save and log if status changed
        if ($originalStatus !== $invoice->payment_status) {
            $invoice->save();

            activity()
                ->performedOn($invoice)
                ->withProperties([
                    'old_payment_status' => $originalStatus,
                    'new_payment_status' => $invoice->payment_status,
                    'balance_due' => $invoice->balance_due,
                    'trigger' => 'credit_note_application',
                ])
                ->log('Invoice payment status updated');
        }
    }

    /**
     * Create payment allocation for credit note application.
     */
    private function createPaymentAllocationForCreditNote(
        CreditNote $creditNote,
        float $amount,
        User $user
    ): void {
        // This would integrate with the existing payment allocation system
        // For now, we'll create a simple record in the credit_note_applications table

        DB::table('acct.payment_allocations')->insert([
            'id' => str()->uuid(),
            'invoice_id' => $creditNote->invoice_id,
            'amount' => $amount,
            'allocation_type' => 'credit_note',
            'reference_id' => $creditNote->id,
            'allocated_at' => now(),
            'allocated_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse credit note application.
     */
    public function reverseCreditNoteApplication(
        CreditNoteApplication $application,
        string $reason,
        User $user
    ): bool {
        $this->authService->canAccessCompany($user, $application->creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.reverse');

        DB::beginTransaction();

        try {
            // Restore invoice balance
            $invoice = $application->invoice;
            $invoice->balance_due += $application->amount_applied;

            // Update payment status
            $this->updateInvoicePaymentStatus($invoice);
            $invoice->save();

            // Create reversal record
            DB::table('acct.credit_note_application_reversals')->insert([
                'id' => str()->uuid(),
                'original_application_id' => $application->id,
                'credit_note_id' => $application->credit_note_id,
                'invoice_id' => $application->invoice_id,
                'amount_reversed' => $application->amount_applied,
                'reason' => $reason,
                'reversed_by' => $user->id,
                'reversed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Delete the original application
            $application->delete();

            // Log the reversal
            activity()
                ->performedOn($application->creditNote)
                ->causedBy($user)
                ->withProperties([
                    'action' => 'credit_note_application_reversed',
                    'amount_reversed' => $application->amount_applied,
                    'reason' => $reason,
                    'invoice_balance_restored' => $application->amount_applied,
                ])
                ->log('Credit note application reversed');

            DB::commit();

            return true;
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Generate PDF for a credit note.
     */
    public function generateCreditNotePdf(CreditNote $creditNote, User $user, array $options = []): string
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        return $this->pdfService->generateCreditNotePdf($creditNote, $user, $options);
    }

    /**
     * Generate PDFs for multiple credit notes.
     */
    public function generateBatchPdfs(array $creditNotes, User $user, array $options = []): array
    {
        if (empty($creditNotes)) {
            throw new \InvalidArgumentException('No credit notes provided for batch PDF generation');
        }

        // Verify all credit notes belong to the same company
        $company = $creditNotes[0]->company;
        foreach ($creditNotes as $creditNote) {
            if ($creditNote->company_id !== $company->id) {
                throw new \InvalidArgumentException('All credit notes must belong to the same company');
            }
        }

        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        return $this->pdfService->generateBatchPdfs($creditNotes, $user, $options);
    }

    /**
     * Generate combined PDF for multiple credit notes.
     */
    public function generateCombinedPdf(array $creditNotes, User $user, array $options = []): string
    {
        if (empty($creditNotes)) {
            throw new \InvalidArgumentException('No credit notes provided for combined PDF generation');
        }

        // Verify all credit notes belong to the same company
        $company = $creditNotes[0]->company;
        foreach ($creditNotes as $creditNote) {
            if ($creditNote->company_id !== $company->id) {
                throw new \InvalidArgumentException('All credit notes must belong to the same company');
            }
        }

        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        return $this->pdfService->generateCombinedPdf($creditNotes, $user, $options);
    }

    /**
     * Get PDF generation settings for company.
     */
    public function getPdfSettings(Company $company): array
    {
        return [
            'paper_size' => $company->pdf_settings['paper_size'] ?? 'a4',
            'orientation' => $company->pdf_settings['orientation'] ?? 'portrait',
            'font' => $company->pdf_settings['font'] ?? 'sans-serif',
            'filename_template' => $company->pdf_settings['filename_template'] ?? 'credit-note-{number}-{date}',
            'show_logo' => $company->pdf_settings['show_logo'] ?? true,
            'show_watermark' => $company->pdf_settings['show_watermark'] ?? false,
            'watermark_text' => $company->pdf_settings['watermark_text'] ?? null,
            'show_amount_in_words' => $company->pdf_settings['show_amount_in_words'] ?? true,
            'show_barcode' => $company->pdf_settings['show_barcode'] ?? true,
            'show_qr_code' => $company->pdf_settings['show_qr_code'] ?? false,
            'color_scheme' => $company->pdf_settings['color_scheme'] ?? 'default',
            'footer_text' => $company->pdf_settings['footer_text'] ?? null,
        ];
    }

    /**
     * Update PDF settings for company.
     */
    public function updatePdfSettings(Company $company, array $settings, User $user): void
    {
        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'company.settings.update');

        $currentSettings = $company->pdf_settings ?? [];
        $updatedSettings = array_merge($currentSettings, $settings);

        $company->pdf_settings = $updatedSettings;
        $company->save();

        activity()
            ->performedOn($company)
            ->causedBy($user)
            ->withProperties([
                'action' => 'pdf_settings_updated',
                'settings' => $settings,
            ])
            ->log('PDF settings updated');
    }

    /**
     * Send credit note via email.
     */
    public function sendCreditNoteEmail(CreditNote $creditNote, User $user, array $options = []): array
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        return $this->emailService->sendCreditNoteEmail($creditNote, $user, $options);
    }

    /**
     * Send multiple credit notes via email.
     */
    public function sendBatchCreditNoteEmails(array $creditNotes, User $user, array $options = []): array
    {
        if (empty($creditNotes)) {
            throw new \InvalidArgumentException('No credit notes provided for batch email');
        }

        // Verify all credit notes belong to the same company
        $company = $creditNotes[0]->company;
        foreach ($creditNotes as $creditNote) {
            if ($creditNote->company_id !== $company->id) {
                throw new \InvalidArgumentException('All credit notes must belong to the same company');
            }
        }

        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        return $this->emailService->sendBatchCreditNoteEmails($creditNotes, $user, $options);
    }

    /**
     * Schedule credit note email to be sent later.
     */
    public function scheduleCreditNoteEmail(CreditNote $creditNote, User $user, \DateTimeInterface $sendAt, array $options = []): bool
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        return $this->emailService->scheduleCreditNoteEmail($creditNote, $user, $sendAt, $options);
    }

    /**
     * Send reminder emails for unpaid credit notes.
     */
    public function sendUnpaidCreditNoteReminders(Company $company, User $user, array $options = []): array
    {
        $this->authService->canAccessCompany($user, $company);
        $this->authService->hasPermission($user, 'credit_notes.email');

        return $this->emailService->sendUnpaidCreditNoteReminder($company, $user, $options);
    }

    /**
     * Process scheduled emails.
     */
    public function processScheduledEmails(): array
    {
        return $this->emailService->processScheduledEmails();
    }

    /**
     * Generate and email credit note in one operation.
     */
    public function generateAndEmailCreditNote(CreditNote $creditNote, User $user, array $emailOptions = [], array $pdfOptions = []): array
    {
        $this->authService->canAccessCompany($user, $creditNote->company);
        $this->authService->hasPermission($user, 'credit_notes.email');
        $this->authService->hasPermission($user, 'credit_notes.pdf');

        // Generate PDF first
        $pdfPath = $this->pdfService->generateCreditNotePdf($creditNote, $user, $pdfOptions);

        // Include the generated PDF in email options
        $emailOptions['pdf_path'] = $pdfPath;

        // Send email
        return $this->emailService->sendCreditNoteEmail($creditNote, $user, $emailOptions);
    }
}
