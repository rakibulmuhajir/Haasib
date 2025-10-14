<?php

namespace Modules\Accounting\Services;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    /**
     * Record a standalone payment (optionally linked to an invoice).
     */
    public function recordPayment(array $data, ?Invoice $invoice = null): Payment
    {
        $validator = Validator::make($data, [
            'company_id' => ['required', 'uuid'],
            'customer_id' => ['required', 'uuid'],
            'payment_method' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:0'],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'string', 'max:3'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return DB::transaction(function () use ($validator, $invoice) {
            $payload = $validator->validated();
            $payload['payment_number'] = Payment::generatePaymentNumber($payload['company_id']);
            $payload['status'] = 'completed';

            if ($invoice) {
                $payload['paymentable_id'] = $invoice->id;
                $payload['paymentable_type'] = Invoice::class;
            }

            $payment = Payment::create($payload);

            if ($invoice) {
                $invoice->calculateTotals();
                $invoice->refresh();

                if ($invoice->balance_due <= 0) {
                    $invoice->markAsPaid();
                }
            }

            return $payment;
        });
    }

    public function markAsFailed(Payment $payment, ?User $performedBy = null): Payment
    {
        $payment->markAsFailed();

        return $payment->fresh();
    }

    public function listPayments(?string $companyId = null, int $perPage = 25): LengthAwarePaginator
    {
        $query = Payment::query()->orderByDesc('payment_date');

        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query->paginate($perPage);
    }
}
