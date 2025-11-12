<?php

namespace App\Http\Controllers;

use App\Models\Vendor;
use App\Models\VendorContact;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Inertia\Inertia;

class VendorController extends Controller
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
        $query = Vendor::where('company_id', Auth::user()->current_company_id)
            ->with(['primaryContact']);

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('legal_name', 'ILIKE', "%{$search}%")
                    ->orWhere('display_name', 'ILIKE', "%{$search}%")
                    ->orWhere('vendor_code', 'ILIKE', "%{$search}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by vendor type
        if ($request->filled('vendor_type')) {
            $query->where('vendor_type', $request->input('vendor_type'));
        }

        $vendors = $query->orderBy('legal_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Vendors/Index', [
            'vendors' => $vendors,
            'filters' => $request->only(['search', 'status', 'vendor_type']),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return Inertia::render('Vendors/Create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:acct.vendors,vendor_code',
            'tax_id' => 'nullable|string|max:50',
            'vendor_type' => 'required|in:individual,company,other',
            'status' => 'required|in:active,inactive,suspended',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.first_name' => 'required|string|max:100',
            'contacts.*.last_name' => 'required|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.mobile' => 'nullable|string|max:50',
            'contacts.*.contact_type' => 'required|in:primary,billing,technical,other',
        ]);

        // Generate unique vendor code if not provided
        if (empty($validated['vendor_code'])) {
            $validated['vendor_code'] = 'V'.str_pad(random_int(1000, 99999), 5, '0', STR_PAD_LEFT);
        }

        $vendor = Vendor::create([
            'id' => (string) Str::uuid(),
            'company_id' => Auth::user()->current_company_id,
            'vendor_code' => $validated['vendor_code'],
            'legal_name' => $validated['legal_name'],
            'display_name' => $validated['display_name'] ?? $validated['legal_name'],
            'tax_id' => $validated['tax_id'] ?? null,
            'vendor_type' => $validated['vendor_type'],
            'status' => $validated['status'],
            'website' => $validated['website'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Create contacts
        foreach ($validated['contacts'] as $index => $contactData) {
            VendorContact::create([
                'id' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'contact_type' => $contactData['contact_type'],
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile' => $contactData['mobile'] ?? null,
                'is_primary' => $index === 0 || $contactData['contact_type'] === 'primary',
            ]);
        }

        return redirect()->route('vendors.show', $vendor)
            ->with('success', 'Vendor created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Vendor $vendor)
    {
        // Ensure user can only view vendors from their company
        if ($vendor->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $vendor->load(['contacts', 'purchaseOrders', 'bills']);

        return Inertia::render('Vendors/Show', [
            'vendor' => $vendor,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Vendor $vendor)
    {
        // Ensure user can only edit vendors from their company
        if ($vendor->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $vendor->load(['contacts']);

        return Inertia::render('Vendors/Edit', [
            'vendor' => $vendor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Vendor $vendor)
    {
        // Ensure user can only update vendors from their company
        if ($vendor->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'vendor_code' => 'required|string|max:50|unique:acct.vendors,vendor_code,'.$vendor->id,
            'tax_id' => 'nullable|string|max:50',
            'vendor_type' => 'required|in:individual,company,other',
            'status' => 'required|in:active,inactive,suspended',
            'website' => 'nullable|url|max:255',
            'notes' => 'nullable|string',
            'contacts' => 'required|array|min:1',
            'contacts.*.first_name' => 'required|string|max:100',
            'contacts.*.last_name' => 'required|string|max:100',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.phone' => 'nullable|string|max:50',
            'contacts.*.mobile' => 'nullable|string|max:50',
            'contacts.*.contact_type' => 'required|in:primary,billing,technical,other',
        ]);

        $vendor->update([
            'legal_name' => $validated['legal_name'],
            'display_name' => $validated['display_name'] ?? $validated['legal_name'],
            'vendor_code' => $validated['vendor_code'],
            'tax_id' => $validated['tax_id'] ?? null,
            'vendor_type' => $validated['vendor_type'],
            'status' => $validated['status'],
            'website' => $validated['website'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Delete existing contacts and recreate them
        $vendor->contacts()->delete();

        foreach ($validated['contacts'] as $index => $contactData) {
            VendorContact::create([
                'id' => (string) Str::uuid(),
                'vendor_id' => $vendor->id,
                'contact_type' => $contactData['contact_type'],
                'first_name' => $contactData['first_name'],
                'last_name' => $contactData['last_name'],
                'email' => $contactData['email'] ?? null,
                'phone' => $contactData['phone'] ?? null,
                'mobile' => $contactData['mobile'] ?? null,
                'is_primary' => $index === 0 || $contactData['contact_type'] === 'primary',
            ]);
        }

        return redirect()->route('vendors.show', $vendor)
            ->with('success', 'Vendor updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Vendor $vendor)
    {
        // Ensure user can only delete vendors from their company
        if ($vendor->company_id !== Auth::user()->current_company_id) {
            abort(403);
        }

        // Check if vendor has transactions before allowing deletion
        if ($vendor->purchaseOrders()->exists() || $vendor->bills()->exists()) {
            return back()->with('error', 'Cannot delete vendor with existing transactions.');
        }

        // Delete contacts first (due to foreign key constraint)
        $vendor->contacts()->delete();

        // Delete the vendor
        $vendor->delete();

        return redirect()->route('vendors.index')
            ->with('success', 'Vendor deleted successfully.');
    }
}
