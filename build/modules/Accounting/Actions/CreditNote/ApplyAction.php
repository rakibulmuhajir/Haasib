<?php

namespace App\Modules\Accounting\Actions\CreditNote;

use App\Contracts\PaletteAction;
use App\Constants\Permissions;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\CreditNote;
use App\Modules\Accounting\Models\CreditNoteApplication;
use App\Modules\Accounting\Models\Invoice;
use App\Support\PaletteFormatter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ApplyAction implements PaletteAction
{
    public function rules(): array
    {
        return [
            'credit_note' => 'required|string|max:255',
            'invoice' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'notes' => 'nullable|string',
            'applied_at' => 'nullable|date',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::CREDIT_NOTE_APPLY;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $credit = $this->resolveCredit($params['credit_note'], $company->id);
        $invoice = $this->resolveInvoice($params['invoice'], $company->id);

        if ($credit->customer_id !== $invoice->customer_id) {
            throw new \Exception('Credit note and invoice must belong to the same customer');
        }

        if ($credit->status === 'void') {
            throw new \Exception('Cannot apply a void credit note');
        }

        if ($invoice->status === 'paid' || $invoice->status === 'void' || $invoice->status === 'cancelled') {
            throw new \Exception('Cannot apply credit to a paid/void/cancelled invoice');
        }

        $amount = (float) $params['amount'];
        $appliedAt = !empty($params['applied_at']) ? Carbon::parse($params['applied_at']) : now();

        $remainingCredit = $credit->amount - $this->appliedTotal($credit->id);
        if ($amount > $remainingCredit) {
            throw new \Exception("Amount exceeds available credit (" . PaletteFormatter::money($remainingCredit, $credit->base_currency) . ")");
        }

        if ($amount > $invoice->balance) {
            throw new \Exception("Amount exceeds invoice balance (" . PaletteFormatter::money($invoice->balance, $invoice->currency) . ")");
        }

        return DB::transaction(function () use ($params, $credit, $invoice, $amount, $appliedAt) {
            $before = $invoice->balance;
            $after = max(0, $invoice->balance - $amount);

            CreditNoteApplication::create([
                'company_id' => $credit->company_id,
                'credit_note_id' => $credit->id,
                'invoice_id' => $invoice->id,
                'amount_applied' => $amount,
                'applied_at' => $appliedAt,
                'user_id' => Auth::id(),
                'notes' => $params['notes'] ?? null,
                'invoice_balance_before' => $before,
                'invoice_balance_after' => $after,
            ]);

            $invoice->update([
                'paid_amount' => $invoice->total_amount - $after,
                'balance' => $after,
                'status' => $after <= 0 ? 'paid' : 'partial',
                'paid_at' => $after <= 0 ? now() : null,
            ]);

            // Update credit status if fully applied
            $totalApplied = $this->appliedTotal($credit->id);
            if ($totalApplied >= $credit->amount) {
                $credit->update(['status' => 'applied']);
            } elseif ($credit->status === 'draft') {
                $credit->update(['status' => 'issued']);
            }

            return [
                'message' => "Applied " . PaletteFormatter::money($amount, $credit->base_currency) .
                    " to invoice {$invoice->invoice_number}",
                'data' => [
                    'credit_note' => $credit->credit_note_number,
                    'invoice' => $invoice->invoice_number,
                    'amount' => $amount,
                    'invoice_balance' => $after,
                ],
            ];
        });
    }

    private function appliedTotal(string $creditNoteId): float
    {
        return (float) CreditNoteApplication::where('credit_note_id', $creditNoteId)->sum('amount_applied');
    }

    private function resolveCredit(string $identifier, string $companyId): CreditNote
    {
        if (Str::isUuid($identifier)) {
            $credit = CreditNote::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($credit) return $credit;
        }

        $credit = CreditNote::where('company_id', $companyId)
            ->where('credit_note_number', $identifier)
            ->first();
        if ($credit) return $credit;

        $credit = CreditNote::where('company_id', $companyId)
            ->where('credit_note_number', 'like', "%{$identifier}")
            ->first();
        if ($credit) return $credit;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Credit note not found: {$identifier}");
    }

    private function resolveInvoice(string $identifier, string $companyId): Invoice
    {
        if (Str::isUuid($identifier)) {
            $invoice = Invoice::where('id', $identifier)
                ->where('company_id', $companyId)
                ->first();
            if ($invoice) return $invoice;
        }

        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', $identifier)
            ->first();
        if ($invoice) return $invoice;

        $invoice = Invoice::where('company_id', $companyId)
            ->where('invoice_number', 'like', "%{$identifier}")
            ->first();
        if ($invoice) return $invoice;

        throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Invoice not found: {$identifier}");
    }
}
