<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Commands\SaveHotelConfirmation;
use App\Modules\Umrah\Http\Requests\SaveHotelConfirmationRequest;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

class HotelConfirmationController extends Controller
{
    public function transport(\App\Modules\Umrah\Http\Requests\SaveTransportConfirmationRequest $request, string $companySlug, string $group): RedirectResponse
    {
        try {
            Bus::dispatch(new \App\Modules\Umrah\Commands\SaveTransportConfirmation(app(CurrentCompany::class)->get()->id, $group, $request->user(), $request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The transport booking could not be updated. Please try again.');
        }

        return back()->with('success', 'Transport booking updated. No charges or payments changed.');
    }

    public function store(SaveHotelConfirmationRequest $request, string $companySlug, string $voucher): RedirectResponse
    {
        try {
            Bus::dispatch(new SaveHotelConfirmation(app(CurrentCompany::class)->get()->id, $voucher, $request->user()->id, $request->validated()));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'The hotel confirmation could not be saved. Please try again.');
        }

        return back()->with('success', 'Hotel confirmation saved.');
    }
}
