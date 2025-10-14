<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use Illuminate\Http\Request;

class InvoiceLookupController extends Controller
{
    public function suggest(Request $request)
    {
        $user = $request->user();
        $q = (string) $request->query('q', '');
        $companyId = $request->query('company_id');
        $customerId = $request->query('customer_id');
        $status = $request->query('status');
        $limit = (int) $request->query('limit', 8);

        $query = Invoice::query();

        if ($q !== '') {
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query->where(function ($w) use ($like) {
                $w->where('invoice_number', 'ilike', $like)
                    ->orWhere('reference_number', 'ilike', $like);
            });
        }

        // Non-superadmin: restrict to current company
        if (! $user->isSuperAdmin()) {
            $companyId = $companyId ?: $request->session()->get('current_company_id');
            abort_if(! $companyId, 422, 'Company context required');
            $query->where('company_id', $companyId);
        } elseif ($companyId) {
            // Superadmin with specific company filter
            $query->where('company_id', $companyId);
        }

        if ($customerId) {
            $query->where('customer_id', $customerId);
        }

        if ($status && in_array($status, ['draft', 'posted', 'cancelled'])) {
            $query->where('status', $status);
        }

        $invoices = $query->whereNot('status', 'cancelled')
            ->orderBy('invoice_date', 'desc')
            ->limit($limit)
            ->get(['invoice_id', 'invoice_number', 'customer_id', 'invoice_date', 'total_amount', 'status', 'balance_due']);

        return response()->json(['data' => $invoices]);
    }

    public function show(Request $request, string $invoice)
    {
        $actor = $request->user();

        $invoice = Invoice::with(['customer', 'items'])
            ->findOrFail($invoice);

        // Check permissions
        if (! $actor->isSuperAdmin()) {
            $companyId = $request->session()->get('current_company_id');
            abort_unless($companyId === $invoice->company_id, 403);
        }

        return response()->json([
            'data' => [
                'id' => $invoice->invoice_id,
                'invoice_number' => $invoice->invoice_number,
                'customer_id' => $invoice->customer_id,
                'customer_name' => $invoice->customer->name ?? null,
                'invoice_date' => $invoice->invoice_date,
                'due_date' => $invoice->due_date,
                'total_amount' => $invoice->total_amount,
                'balance_due' => $invoice->balance_due,
                'status' => $invoice->status,
                'payment_status' => $invoice->payment_status,
                'created_at' => $invoice->created_at,
            ],
        ]);
    }
}
