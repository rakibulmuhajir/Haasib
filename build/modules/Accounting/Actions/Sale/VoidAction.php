<?php

namespace App\Modules\Accounting\Actions\Sale;

use App\Constants\Permissions;
use App\Contracts\DescribesPalette;
use App\Contracts\PaletteAction;
use App\Facades\CompanyContext;
use App\Modules\Accounting\Models\Invoice;
use App\Modules\Accounting\Models\Payment;
use App\Modules\Accounting\Models\PaymentAllocation;
use App\Services\AccountingWriteTransaction;
use App\Services\CommandBus;
use App\Support\CompanyClock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Takes back a cash sale: voids the payment it was settled by, then the invoice, in one database
 * transaction (so a refusal part-way leaves both as they were). Both go through their own void
 * commands, so their rules, locks, withholding lines and permissions are exactly theirs.
 * Needs invoice.void and payment.void.
 */
class VoidAction implements DescribesPalette, PaletteAction
{
    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'reason' => 'nullable|string|max:500',
        ];
    }

    public function permission(): ?string
    {
        return Permissions::INVOICE_VOID;
    }

    public function handle(array $params): array
    {
        $company = CompanyContext::requireCompany();

        $user = Auth::user();
        if ($user && method_exists($user, 'hasCompanyPermission') && ! $user->hasCompanyPermission(Permissions::PAYMENT_VOID)) {
            throw new AuthorizationException('Permission denied: '.Permissions::PAYMENT_VOID);
        }

        return AccountingWriteTransaction::run(function () use ($company, $user, $params) {
            $invoice = Invoice::where('company_id', $company->id)->with('customer')->find($params['id']);
            if (! $invoice) {
                throw ValidationException::withMessages(['id' => 'Sale not found.']);
            }
            if (strtolower((string) $invoice->customer?->name) !== 'cash sales') {
                throw ValidationException::withMessages(['id' => 'That is not a cash sale.']);
            }
            if (in_array($invoice->status, ['void', 'cancelled'], true)) {
                throw ValidationException::withMessages(['id' => 'This sale is already undone.']);
            }

            // The payments that settled it. One that also paid other invoices is not ours to void.
            $paymentIds = PaymentAllocation::where('invoice_id', $invoice->id)->pluck('payment_id')->unique()->values();
            foreach ($paymentIds as $paymentId) {
                if (PaymentAllocation::where('payment_id', $paymentId)->where('invoice_id', '!=', $invoice->id)->exists()) {
                    throw ValidationException::withMessages(['id' => 'Its payment also settled other invoices. Void the payment first.']);
                }
            }
            $payments = Payment::where('company_id', $company->id)->whereIn('id', $paymentIds)->get();

            $bus = app(CommandBus::class);
            $reason = $params['reason'] ?? null;
            foreach ($payments as $payment) {
                $bus->dispatch('payment.void', ['id' => $payment->id, 'reason' => $reason], $user);
            }
            $bus->dispatch('invoice.void', [
                'id' => $invoice->id,
                'reason' => $reason,
                'reversal_date' => app(CompanyClock::class)->today(),
            ], $user);

            return [
                'message' => "Sale undone: {$invoice->invoice_number}",
                'data' => [
                    'id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                    'payment_ids' => $payments->pluck('id')->all(),
                ],
                'redirect' => "/{$company->slug}/invoices/{$invoice->id}",
            ];
        });
    }

    public function palette(): array
    {
        return [
            'description' => 'Undo a cash sale',
            'fields' => [
                'id' => ['hint' => 'Sale invoice id'],
                'reason' => ['hint' => 'Why'],
            ],
        ];
    }
}
