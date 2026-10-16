<?php

namespace App\Modules\FuelStation\Http;

use App\Modules\Accounting\Http\Requests\StorePaymentRequest;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Actions\AmanatMovementAction;
use App\Services\CommandBus;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * The "amanat" way to receive a customer payment, registered with the core's
 * PaymentReceiptModes by the FuelStation provider.
 */
class AmanatReceiptMode
{
    /**
     * Whether this company/user combination may hold customer payments as amanat instead
     * of applying them to invoices: the fuel_station module is on (module enabled OR
     * industry_code/industry === 'fuel_station', see Company::hasModule), plus the
     * permission AmanatMovementAction itself requires.
     */
    public function available($company, $user): bool
    {
        if (! $user) {
            return false;
        }

        $isFuelStation = $company->hasModule('fuel_station');

        if (! $isFuelStation) {
            return false;
        }

        $permission = (new AmanatMovementAction())->permission();

        return ! $permission || $user->hasCompanyPermission($permission);
    }

    /**
     * Record money received from a customer as amanat (an advance held for them, used up
     * against fuel later) instead of applying it to invoices. Reuses the FuelStation
     * module's own command — no journal, payment row or amanat row is written here.
     */
    public function store(StorePaymentRequest $request, $company, array $validated): RedirectResponse
    {
        $commandBus = app(CommandBus::class);
        $user = $request->user();

        if (! $this->available($company, $user)) {
            throw ValidationException::withMessages([
                'received_as' => 'Amanat is not available for this company.',
            ]);
        }

        try {
            $commandBus->dispatch('fuel.amanat.movement', [
                'customer_id' => $validated['customer_id'],
                'kind' => 'deposit',
                'business_date' => $validated['payment_date'] ?? null,
                'amount' => $validated['amount'],
                'payment_account_id' => $validated['deposit_account_id'],
                'reference' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ], $user);

            $customer = Customer::where('company_id', $company->id)->findOrFail($validated['customer_id']);

            return redirect()
                ->route('fuel.amanat.show', ['company' => $company->slug, 'customer' => $customer->id])
                ->with('success', "Amanat received from {$customer->name}");
        } catch (ModelNotFoundException $e) {
            $field = $e->getModel() === Customer::class ? 'customer_id' : 'deposit_account_id';

            return redirect()
                ->back()
                ->withErrors([$field => $field === 'customer_id' ? 'The selected customer was not found.' : 'The selected deposit account was not found.'])
                ->withInput();
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->back()
                ->withErrors(['customer_id' => $e->getMessage()])
                ->withInput();
        } catch (ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->with('error', $e->getMessage())
                ->withInput();
        }
    }
}
