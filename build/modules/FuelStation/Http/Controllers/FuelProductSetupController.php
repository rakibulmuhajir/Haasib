<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreFuelProductSetupRequest;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FuelProductSetupController extends Controller
{
    public function store(StoreFuelProductSetupRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->getOrFail();

        try {
            $result = app(CommandBus::class)->dispatch(
                'fuel.products.setup',
                $request->validated(),
                $request->user()
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::error('Fuel product setup failed', [
                'company_id' => $company->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Failed to save products. Please try again.');
        }

        $message = $result['message'] ?? 'Products saved successfully.';

        return back()->with('success', $message);
    }
}
