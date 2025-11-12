<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class PurchaseOrderController extends Controller
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
        $query = PurchaseOrder::where('company_id', Auth::user()->current_company_id)
            ->with(['vendor']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'ILIKE', "%{$search}%")
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
            $query->whereDate('order_date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('order_date', '<=', $request->input('date_to'));
        }

        $purchaseOrders = $query->orderBy('order_date', 'desc')
            ->paginate(25)
            ->withQueryString();

        // Get vendors for filter dropdown
        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('PurchaseOrders/Index', [
            'purchaseOrders' => $purchaseOrders,
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'status', 'vendor_id', 'date_from', 'date_to']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        $vendorId = $request->input('vendor_id');
        $vendor = null;

        if ($vendorId) {
            $vendor = Vendor::where('company_id', Auth::user()->current_company_id)
                ->findOrFail($vendorId);
        }

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('PurchaseOrders/Create', [
            'vendors' => $vendors,
            'selectedVendor' => $vendor,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
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

            // Generate unique PO number
            $year = date('Y');
            $sequence = DB::table('acct.purchase_orders')
                ->whereYear('order_date', $year)
                ->where('company_id', Auth::user()->current_company_id)
                ->count() + 1;

            $poNumber = 'PO-'.$year.'-'.str_pad($sequence, 5, '0', STR_PAD_LEFT);

            $status = $validated['action'] === 'draft' ? 'draft' : 'pending_approval';

            $po = PurchaseOrder::create([
                'id' => (string) Str::uuid(),
                'company_id' => Auth::user()->current_company_id,
                'po_number' => $poNumber,
                'vendor_id' => $validated['vendor_id'],
                'status' => $status,
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'created_by' => Auth::id(),
                'approved_by' => $status === 'pending_approval' ? Auth::id() : null,
                'approved_at' => $status === 'pending_approval' ? now() : null,
            ]);

            // Create PO lines
            foreach ($validated['lines'] as $index => $lineData) {
                PurchaseOrderLine::create([
                    'id' => (string) Str::uuid(),
                    'po_id' => $po->id,
                    'line_number' => $index + 1,
                    'product_id' => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'discount_percentage' => $lineData['discount_percentage'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                ]);
            }

            // Recalculate totals
            $po->recalculateTotals();

            DB::commit();

            return redirect()->route('purchase-orders.show', $po)
                ->with('success', 'Purchase Order created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to create Purchase Order: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only view POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $purchaseOrder->load([
            'vendor',
            'lines',
            'approvedBy',
            'createdBy',
        ]);

        return Inertia::render('PurchaseOrders/Show', [
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only edit POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if PO can be edited
        if (! $purchaseOrder->canBeEdited()) {
            abort(403, 'This Purchase Order cannot be edited in its current status.');
        }

        $purchaseOrder->load(['vendor', 'lines']);

        $vendors = Vendor::where('company_id', Auth::user()->current_company_id)
            ->where('status', 'active')
            ->orderBy('legal_name')
            ->get(['id', 'legal_name', 'display_name']);

        return Inertia::render('PurchaseOrders/Edit', [
            'purchaseOrder' => $purchaseOrder,
            'vendors' => $vendors,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only update POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if PO can be edited
        if (! $purchaseOrder->canBeEdited()) {
            abort(403, 'This Purchase Order cannot be edited in its current status.');
        }

        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendors,id',
            'order_date' => 'required|date',
            'expected_delivery_date' => 'nullable|date|after_or_equal:order_date',
            'currency' => 'required|string|size:3',
            'exchange_rate' => 'required|numeric|min:0',
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

            $purchaseOrder->update([
                'vendor_id' => $validated['vendor_id'],
                'status' => $status,
                'order_date' => $validated['order_date'],
                'expected_delivery_date' => $validated['expected_delivery_date'] ?? null,
                'currency' => $validated['currency'],
                'exchange_rate' => $validated['exchange_rate'],
                'notes' => $validated['notes'] ?? null,
                'internal_notes' => $validated['internal_notes'] ?? null,
                'approved_by' => $status === 'pending_approval' ? Auth::id() : null,
                'approved_at' => $status === 'pending_approval' ? now() : null,
            ]);

            // Delete existing lines
            $purchaseOrder->lines()->delete();

            // Create new lines
            foreach ($validated['lines'] as $index => $lineData) {
                PurchaseOrderLine::create([
                    'id' => (string) Str::uuid(),
                    'po_id' => $purchaseOrder->id,
                    'line_number' => $index + 1,
                    'product_id' => $lineData['product_id'] ?? null,
                    'description' => $lineData['description'],
                    'quantity' => $lineData['quantity'],
                    'unit_price' => $lineData['unit_price'],
                    'discount_percentage' => $lineData['discount_percentage'] ?? 0,
                    'tax_rate' => $lineData['tax_rate'] ?? 0,
                ]);
            }

            // Recalculate totals
            $purchaseOrder->recalculateTotals();

            DB::commit();

            return redirect()->route('purchase-orders.show', $purchaseOrder)
                ->with('success', 'Purchase Order updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to update Purchase Order: '.$e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only delete POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if PO can be cancelled
        if (! $purchaseOrder->canBeCancelled()) {
            return back()->with('error', 'This Purchase Order cannot be cancelled in its current status.');
        }

        try {
            DB::beginTransaction();

            // Delete lines first (due to foreign key constraint)
            $purchaseOrder->lines()->delete();

            // Update status to cancelled instead of deleting
            $purchaseOrder->update([
                'status' => 'cancelled',
                'notes' => ($purchaseOrder->notes ?? '').' - Cancelled on '.now()->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return redirect()->route('purchase-orders.index')
                ->with('success', 'Purchase Order cancelled successfully.');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Failed to cancel Purchase Order: '.$e->getMessage());
        }
    }

    /**
     * Approve the purchase order.
     */
    public function approve(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only approve POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $purchaseOrder->canBeApproved()) {
            abort(403, 'This Purchase Order cannot be approved in its current status.');
        }

        $purchaseOrder->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Purchase Order approved successfully.');
    }

    /**
     * Send the purchase order to vendor.
     */
    public function send(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only send POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        if (! $purchaseOrder->canBeSent()) {
            abort(403, 'This Purchase Order cannot be sent in its current status.');
        }

        $purchaseOrder->update([
            'status' => 'sent',
            'sent_to_vendor_at' => now(),
        ]);

        return back()->with('success', 'Purchase Order sent to vendor successfully.');
    }

    /**
     * Generate PDF of the purchase order.
     */
    public function generatePdf(PurchaseOrder $purchaseOrder)
    {
        // Ensure user can only view POs from their company
        if ($purchaseOrder->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $purchaseOrder->load(['vendor', 'lines']);

        // TODO: Implement PDF generation using DomPDF or similar
        return response('PDF generation not yet implemented', 501);
    }
}
