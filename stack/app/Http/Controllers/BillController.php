<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillLine;
use App\Models\Acct\PurchaseOrder;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class BillController extends Controller
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
        $query = Bill::where('company_id', Auth::user()->current_company_id)
            ->with(['vendor']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('bill_number', 'ILIKE', "%{$search}%")
                    ->orWhere('vendor_bill_number', 'ILIKE', "%{$search}%")
                    ->orWhere('notes', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by vendor
        if ($request->filled('vendor_id')) {
            $query->where('vendor_id', $request->input('vendor_id'));
        }

        // Filter by date range
        if ($request->filled('date_from')) {
            $query->whereDate('bill_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('bill_date', '<=', $request->input('date_to'));
        }

        // Filter by due date range
        if ($request->filled('due_from')) {
            $query->whereDate('due_date', '>=', $request->input('due_from'));
        }

        if ($request->filled('due_to')) {
            $query->whereDate('due_date', '<=', $request->input('due_to'));
        }

        $bills = $query->orderBy('bill_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Get vendors for filter dropdown
        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Bills/Index', [
            'bills' => $bills,
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'status', 'vendor_id', 'date_from', 'date_to', 'due_from', 'due_to']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $vendorId = $request->input('vendor_id');
        $purchaseOrderId = $request->input('purchase_order_id');
        $vendor = null;
        $purchaseOrder = null;

        if ($vendorId) {
            $vendor = Vendor::where('company_id', Auth::user()->current_company_id)
                ->findOrFail($vendorId);
        }

        if ($purchaseOrderId) {
            $purchaseOrder = PurchaseOrder::where('company_id', Auth::user()->current_company_id)
                ->with(['vendor', 'lines'])
                ->findOrFail($purchaseOrderId);

            if (! $vendor && $purchaseOrder->vendor) {
                $vendor = $purchaseOrder->vendor;
            }
        }

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Bills/Create', [
            'vendors' => $vendors,
            'selectedVendor' => $vendor,
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
            'vendor_bill_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'purchase_order_id' => 'nullable|exists:acct.purchase_orders,id',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:1000',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'action' => 'required|in:draft,submit_for_approval',
        ]);

        try {
            DB::beginTransaction();

            $status = $validated['action'] === 'draft' ? 'draft' : 'pending_approval';

            $bill = Bill::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'vendor_id' => $validated['vendor_id'],
                'status' => $status,
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'purchase_order_id' => $validated['purchase_order_id'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => $status === 'pending_approval' ? Auth::id() : null,
                'approved_at' => $status === 'pending_approval' ? now() : null,
            ]);

            // Create bill lines
            foreach ($validated['lines'] as $index => $lineData) {
                BillLine::create([
                    'id' => (string) Str::uuid(),
                    'bill_id' => $bill->id,
                    'line_number' => $index + 1,
                    'purchase_order_line_id' => $lineData['purchase_order_line_id'] ?? null,
                    'product_id' => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'discount_percentage' => $lineData['discount_percentage'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            // Recalculate totals
            $bill->recalculateTotals();

            DB::commit();

            return redirect()->route('bills.show', $bill)
                ->with('success', 'Bill created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Bill: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Bill $bill)
    {
        // Ensure user can only view bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $bill->load([
            'vendor',
            'lines',
            'lines.purchaseOrderLine',
            'purchaseOrder',
            'approvedBy',
            'createdBy',
        ]);

        return Inertia::render('Bills/Show', [
            'bill' => $bill,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Bill $bill)
    {
        // Ensure user can only edit bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if bill can be edited
        if (! $bill->canBeEdited()) {
            abort(403, 'This Bill cannot be edited in its current status.');
        }

        $bill->load(['vendor', 'lines']);

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('Bills/Edit', [
            'bill' => $bill,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Bill $bill)
    {
        // Ensure user can only update bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if bill can be edited
        if (! $bill->canBeEdited()) {
            abort(403, 'This Bill cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'bill_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:bill_date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
            'vendor_bill_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string|max:1000',
            'lines.*.quantity' => 'required|numeric|min:0',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
            'lines.*.tax_rate' => 'nullable|numeric|min:0|max:100',
            'action' => 'required|in:draft,submit_for_approval',
        ]);

        try {
            DB::beginTransaction();

            $status = $validated['action'] === 'draft' ? 'draft' : 'pending_approval';

            $bill->update([
                'vendor_id' => $validated['vendor_id'],
                'status' => $status,
                'bill_date' => $validated['bill_date'],
                'due_date' => $validated['due_date'],
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'vendor_bill_number' => $validated['vendor_bill_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'approved_by' => $status === 'pending_approval' ? Auth::id() : null,
                'approved_at' => $status === 'pending_approval' ? now() : null,
            ]);

            // Delete existing lines
            $bill->lines()->delete();

            // Create new lines
            foreach ($validated['lines'] as $index => $lineData) {
                BillLine::create([
                    'id' => (string) Str::uuid(),
                    'bill_id' => $bill->id,
                    'line_number' => $index + 1,
                    'purchase_order_line_id' => $lineData['purchase_order_line_id'] ?? null,
                    'product_id' => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'discount_percentage' => $lineData['discount_percentage'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                    'notes' => $lineData['notes'] ?? null,
                ]);
            }

            // Recalculate totals
            $bill->recalculateTotals();

            DB::commit();

            return redirect()->route('bills.show', $bill)
                ->with('success', 'Bill updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Bill: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Bill $bill)
    {
        // Ensure user can only delete bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if bill can be cancelled
        if (! $bill->canBeCancelled()) {
            return back()->with('error', 'This Bill cannot be cancelled in its current status.');
        }

        try {
            DB::beginTransaction();

            // Delete lines first (due to foreign key constraint)
            $bill->lines()->delete();

            // Update status to cancelled instead of deleting
            $bill->update([
                'status' => 'cancelled',
                'notes' => ($bill->notes ?? '').' - Cancelled on '.now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return redirect()->route('bills.index')
                ->with('success', 'Bill cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to cancel Bill: '.$e->getMessage());
        }
    }

    /**
     * Approve the bill.
     */
    public function approve(Bill $bill)
    {
        // Ensure user can only approve bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $bill->canBeApproved()) {
            abort(403, 'This Bill cannot be approved in its current status.');
        }

        $bill->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Bill approved successfully.');
    }

    /**
     * Generate PDF of the bill.
     */
    public function generatePdf(Bill $bill)
    {
        // Ensure user can only view bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $bill->load(['vendor', 'lines']);

        // TODO: Implement PDF generation using DomPDF or similar
        return response('PDF generation not yet implemented', 501);
    }

    /**
     * Show payment form for the bill.
     */
    public function createPayment(Bill $bill)
    {
        // Ensure user can only pay bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $bill->canReceivePayment()) {
            abort(403, 'This Bill cannot be paid in its current status.');
        }

        $bill->load(['vendor', 'payments']);

        return Inertia::render('Bills/Payment', [
            'bill' => $bill,
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
     * Process payment for the bill.
     */
    public function processPayment(Request $request, Bill $bill)
    {
        // Ensure user can only pay bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $bill->canReceivePayment()) {
            abort(403, 'This Bill cannot be paid in its current status.');
        }

        $validated = $request->validate([
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|min:0.01|max:'.$bill->balance_due,
            'payment_method' => 'required|in:cash,check,bank_transfer,credit_card,debit_card,other',
            'reference_number' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create bill payment record
            $payment = BillPayment::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'payment_type' => 'bill_payment',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'currency' => $bill->currency,
                'exchange_rate' => $bill->exchange_rate,
                'payment_method' => $validated['payment_method'],
                'status' => 'completed',
                'description' => $validated['description'] ?? "Payment for bill {$bill->bill_number}",
                'notes' => $validated['notes'],
                'reference_number' => $validated['reference_number'],
                'payable_id' => $bill->id,
                'payable_type' => Bill::class,
                'vendor_id' => $bill->vendor_id,
                'paid_by' => Auth::id(),
                'created_by' => Auth::id(),
            ]);

            // Apply payment to bill (handled automatically by BillPayment model)

            DB::commit();

            return redirect()->route('bills.show', $bill)
                ->with('success', 'Payment of '.number_format($validated['amount'], 2).' has been processed successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to process payment: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show bill payment history.
     */
    public function payments(Bill $bill)
    {
        // Ensure user can only view bills from their company
        if ($bill->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $bill->load(['vendor', 'payments' => function ($query) {
            $query->orderBy('payment_date', 'desc');
        }]);

        return Inertia::render('Bills/Payments', [
            'bill' => $bill,
        ]);
    }
}
