<?php

namespace App\Modules\Accounting\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Http\Requests\StoreCorrectionRequest;
use App\Services\CommandBus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Correct a posted invoice or payment from its own page. It reads as editing the record; the
 * accounting underneath is CorrectionService's.
 */
class CorrectionController extends Controller
{
    public function invoice(StoreCorrectionRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $invoiceId = (string) $request->route('invoice');

        return $this->dispatch($request, $data['action'] === 'split'
            ? ['correction.invoice_split', ['invoice_id' => $invoiceId, 'shares' => $data['shares'], 'reason' => $data['reason']]]
            : ['correction.invoice_customer', ['invoice_id' => $invoiceId, 'customer_id' => $data['customer_id'], 'reason' => $data['reason']]]);
    }

    public function payment(StoreCorrectionRequest $request): RedirectResponse
    {
        $data = $request->validated();

        return $this->dispatch($request, ['correction.payment_customer', [
            'payment_id' => (string) $request->route('payment'),
            'customer_id' => $data['customer_id'],
            'apply_oldest_first' => $data['apply_oldest_first'] ?? true,
            'reason' => $data['reason'],
        ]]);
    }

    private function dispatch(StoreCorrectionRequest $request, array $command): RedirectResponse
    {
        try {
            $result = app(CommandBus::class)->dispatch($command[0], $command[1], $request->user());
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $result['message'] ?? 'Corrected');
    }
}
