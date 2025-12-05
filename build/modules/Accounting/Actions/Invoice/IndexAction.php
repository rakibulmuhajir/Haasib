<?php

namespace App\Modules\Accounting\Actions\Invoice;

use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Customer;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IndexAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'status' => 'nullable|string|in:draft,sent,viewed,partial,paid,overdue,void,cancelled',
            'customer' => 'nullable|string|max:255',
            'unpaid' => 'nullable|boolean',
            'overdue' => 'nullable|boolean',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'limit' => 'nullable|integer|min:1|max:100',
        ];
    }

    public function permission(): ?string
    {
        return null; // Any authenticated user can list
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();
        $limit = $params['limit'] ?? 50;

        $query = Invoice::with('customer')
            ->where('company_id', $company->id)
            ->orderBy('invoice_date', 'desc')
            ->orderBy('invoice_number', 'desc');

        // Status filter
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Unpaid shorthand (draft + sent + viewed + partial + overdue)
        if (!empty($params['unpaid']) && $params['unpaid']) {
            $query->whereIn('status', [
                'draft',
                'sent',
                'viewed',
                'partial',
                'overdue',
            ]);
        }

        // Overdue filter
        if (!empty($params['overdue']) && $params['overdue']) {
            $query->where('status', '!=', 'paid')
                  ->where('status', '!=', 'cancelled')
                  ->where('status', '!=', 'draft')
                  ->where('due_date', '<', now()->startOfDay());
        }

        // Customer filter
        if (!empty($params['customer'])) {
            $query->whereHas('customer', function ($q) use ($params) {
                $q->where('name', 'ilike', "%{$params['customer']}%")
                  ->orWhere('customer_number', 'ilike', "%{$params['customer']}%")
                  ->orWhere('email', 'ilike', "%{$params['customer']}%");
            });
        }

        // Date range
        if (!empty($params['from'])) {
            $query->where('invoice_date', '>=', $params['from']);
        }
        if (!empty($params['to'])) {
            $query->where('invoice_date', '<=', $params['to']);
        }

        $invoices = $query->limit($limit)->get();

        // Calculate totals
        $totalOutstanding = $invoices->sum('balance');
        $totalBilled = $invoices->sum('total_amount');

        return [
            'data' => PaletteFormatter::table(
                headers: ['Number', 'Customer', 'Amount', 'Due', 'Status'],
                rows: $invoices->map(fn($inv) => [
                    $inv->invoice_number,
                    Str::limit($inv->customer->name, 20),
                    PaletteFormatter::money($inv->total_amount, $inv->currency),
                    PaletteFormatter::relativeDate($inv->due_date),
                    $this->formatStatus($inv),
                ])->toArray(),
                footer: $invoices->count() . ' invoices · ' .
                        PaletteFormatter::money($totalOutstanding, $company->base_currency) . ' outstanding',
                rowIds: $invoices->pluck('id')->toArray()
            ),
        ];
    }

    private function formatStatus(Invoice $invoice): string
    {
        // Check if overdue
        $isOverdue = !in_array($invoice->status, [
            'paid',
            'cancelled',
            'void',
            'draft',
        ]) && $invoice->due_date->isPast();

        if ($isOverdue) {
            return '{error}⚠ Overdue{/}';
        }

        return match ($invoice->status) {
            'draft' => '{secondary}○ Draft{/}',
            'sent' => '{warning}◐ Sent{/}',
            'sent' => '{warning}◐ Sent{/}',
            'viewed' => '{accent}◑ Viewed{/}',
            'partial' => '{accent}◑ Partial{/}',
            'overdue' => '{warning}◕ Overdue{/}',
            'paid' => '{success}● Paid{/}',
            'void' => '{secondary}✗ Void{/}',
            'cancelled' => '{secondary}✗ Cancelled{/}',
            default => $invoice->status,
        };
    }
}
