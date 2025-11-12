<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Expense::where('company_id', Auth::user()->current_company_id)
            ->with(['category', 'employee', 'vendor']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('expense_number', 'ILIKE', "%{$search}%")
                    ->orWhere('title', 'ILIKE', "%{$search}%")
                    ->orWhere('description', 'ILIKE', "%{$search}%")
                    ->orWhere('receipt_number', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by category
        if ($request->filled('expense_category_id')) {
            $query->where('expense_category_id', $request->input('expense_category_id'));
        }

        // Filter by employee
        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('expense_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expense_date', '<=', $request->input('date_to'));
        }

        $expenses = $query->orderBy('expense_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Get dropdown data
        $categories = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name']);

        $employees = User::whereHas('companies', function ($q) {
            $q->where('company_id', Auth::user()->current_company_id);
        })->get(['id', 'name']);

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Expenses/Index', [
            'expenses' => $expenses,
            'categories' => $categories,
            'employees' => $employees,
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'status', 'expense_category_id', 'employee_id', 'vendor_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $categories = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'type']);

        $employees = User::whereHas('companies', function ($q) {
            $q->where('company_id', Auth::user()->current_company_id);
        })->get(['id', 'name']);

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Expenses/Create', [
            'categories' => $categories,
            'employees' => $employees,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'expense_category_id' => 'required|exists:acct.expense_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
            'employee_id' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'action' => 'required|in:draft,submit',
        ]);

        try {
            DB::beginTransaction();

            $status = $validated['action'] === 'draft' ? 'draft' : 'submitted';

            $expense = Expense::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'expense_category_id' => $validated['expense_category_id'],
                'status' => $status,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'expense_date' => $validated['expense_date'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'employee_id' => $validated['employee_id'] ?? null,
                'vendor_id' => $validated['vendor_id'] ?? null,
                'receipt_number' => $validated['receipt_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'submitted_by' => $status === 'submitted' ? Auth::id() : null,
                'submitted_at' => $status === 'submitted' ? now() : null,
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return redirect()->route('expenses.show', $expense)
                ->with('success', 'Expense created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Expense: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Expense $expense)
    {
        // Ensure user can only view expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $expense->load([
            'category',
            'employee',
            'vendor',
            'submittedBy',
            'approvedBy',
            'rejectedBy',
            'createdBy',
            'payments',
        ]);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Expense $expense)
    {
        // Ensure user can only edit expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if expense can be edited
        if (! $expense->canBeEdited()) {
            abort(403, 'This Expense cannot be edited in its current status.');
        }

        $expense->load(['category', 'employee', 'vendor']);

        $categories = ExpenseCategory::where('company_id', Auth::user()->current_company_id)
            ->active()
            ->ordered()
            ->get(['id', 'name', 'type']);

        $employees = User::whereHas('companies', function ($q) {
            $q->where('company_id', Auth::user()->current_company_id);
        })->get(['id', 'name']);

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense,
            'categories' => $categories,
            'employees' => $employees,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Expense $expense)
    {
        // Ensure user can only update expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if expense can be edited
        if (! $expense->canBeEdited()) {
            abort(403, 'This Expense cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'expense_category_id' => 'required|exists:acct.expense_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'expense_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
            'employee_id' => 'nullable|exists:users,id',
            'vendor_id' => 'nullable|exists:vendors,id',
            'receipt_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string',
            'action' => 'required|in:draft,submit',
        ]);

        try {
            DB::beginTransaction();

            $status = $validated['action'] === 'draft' ? 'draft' : 'submitted';

            $expense->update([
                'expense_category_id' => $validated['expense_category_id'],
                'status' => $status,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'expense_date' => $validated['expense_date'],
                'amount' => $validated['amount'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'employee_id' => $validated['employee_id'] ?? null,
                'vendor_id' => $validated['vendor_id'] ?? null,
                'receipt_number' => $validated['receipt_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'submitted_by' => $status === 'submitted' ? Auth::id() : $expense->submitted_by,
                'submitted_at' => $status === 'submitted' ? now() : $expense->submitted_at,
            ]);

            DB::commit();

            return redirect()->route('expenses.show', $expense)
                ->with('success', 'Expense updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Expense: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Expense $expense)
    {
        // Ensure user can only delete expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if expense can be deleted
        if (! $expense->canBeDeleted()) {
            return back()->with('error', 'This Expense cannot be deleted in its current status.');
        }

        try {
            DB::beginTransaction();

            // Soft delete the expense
            $expense->delete();

            DB::commit();

            return redirect()->route('expenses.index')
                ->with('success', 'Expense deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to delete Expense: '.$e->getMessage());
        }
    }

    /**
     * Submit the expense for approval.
     */
    public function submit(Expense $expense)
    {
        // Ensure user can only submit expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBeSubmitted()) {
            abort(403, 'This Expense cannot be submitted in its current status.');
        }

        $expense->submit();

        return back()->with('success', 'Expense submitted for approval successfully.');
    }

    /**
     * Approve the expense.
     */
    public function approve(Expense $expense)
    {
        // Ensure user can only approve expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBeApproved()) {
            abort(403, 'This Expense cannot be approved in its current status.');
        }

        $expense->approve();

        return back()->with('success', 'Expense approved successfully.');
    }

    /**
     * Reject the expense.
     */
    public function reject(Request $request, Expense $expense)
    {
        // Ensure user can only reject expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBeRejected()) {
            abort(403, 'This Expense cannot be rejected in its current status.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $expense->reject($validated['rejection_reason']);

        return back()->with('success', 'Expense rejected successfully.');
    }

    /**
     * Mark expense as paid.
     */
    public function markAsPaid(Expense $expense)
    {
        // Ensure user can only mark expenses from their company as paid
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBePaid()) {
            abort(403, 'This Expense cannot be marked as paid in its current status.');
        }

        $expense->markAsPaid();

        return back()->with('success', 'Expense marked as paid successfully.');
    }

    /**
     * Show expense payment form.
     */
    public function createPayment(Expense $expense)
    {
        // Ensure user can only pay expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBePaid()) {
            abort(403, 'This Expense cannot be paid in its current status.');
        }

        $expense->load(['category', 'employee', 'vendor', 'payments']);

        return Inertia::render('Expenses/Payment', [
            'expense' => $expense,
            'paymentMethods' => [
                'cash' => 'Cash',
                'check' => 'Check',
                'bank_transfer' => 'Bank Transfer',
                'credit_card' => 'Credit Card',
                'debit_card' => 'Debit Card',
                'other' => 'Other',
            ],
        ]);
    }

    /**
     * Process expense payment.
     */
    public function processPayment(Request $request, Expense $expense)
    {
        // Ensure user can only pay expenses from their company
        if ($expense->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $expense->canBePaid()) {
            abort(403, 'This Expense cannot be paid in its current status.');
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:cash,check,bank_transfer,credit_card,debit_card,other',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create payment record
            $payment = BillPayment::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'payment_type' => $expense->isEmployeeExpense() ? 'expense_reimbursement' : 'vendor_payment',
                'payment_date' => $validated['payment_date'],
                'amount' => $expense->amount,
                'currency' => $expense->currency,
                'exchange_rate' => $expense->exchange_rate,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'description' => $validated['description'] ?? "Payment for expense {$expense->expense_number}",
                'notes' => $validated['notes'],
                'reference_number' => $validated['reference_number'],
                'payable_id' => $expense->id,
                'payable_type' => Expense::class,
                'employee_id' => $expense->employee_id,
                'vendor_id' => $expense->vendor_id,
                'paid_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            // Apply payment to expense (handled automatically by BillPayment model)

            DB::commit();

            return redirect()->route('expenses.show', $expense)
                ->with('success', 'Payment of '.number_format($expense->amount, 2).' has been processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to process payment: '.$e->getMessage())
                ->withInput();
        }
    }
}
