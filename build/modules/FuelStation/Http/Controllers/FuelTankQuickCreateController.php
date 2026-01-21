<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\FuelStation\Http\Requests\StoreFuelTankQuickCreateRequest;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FuelTankQuickCreateController extends Controller
{
    public function store(StoreFuelTankQuickCreateRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->getOrFail();

        try {
            $result = app(CommandBus::class)->dispatch(
                'fuel.tanks.quick_create',
                $request->validated(),
                $request->user()
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException $exception) {
            return back()->with('error', $exception->getMessage());
        } catch (\Throwable $exception) {
            Log::error('Fuel tank quick create failed', [
                'company_id' => $company->id,
                'error' => $exception->getMessage(),
            ]);

            return back()->with('error', 'Failed to create tank. Please try again.');
        }

        return back()
            ->with('success', $result['message'] ?? 'Tank created successfully.')
            ->with('tank', $result['data']['tank'] ?? null)
            ->with('item', $result['data']['item'] ?? null);
    }
}
