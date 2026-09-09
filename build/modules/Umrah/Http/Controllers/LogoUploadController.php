<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Commands\UploadPartyLogo;
use App\Modules\Umrah\Http\Requests\StoreLogoRequest;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;

/**
 * Uploads a party logo on its own, ahead of the form that will use it.
 *
 * The party forms save with PUT, and a PUT carrying a file arrives with no
 * file: PHP only populates uploads for POST. Rather than teach five forms
 * to spoof their method, the picker sends the image here as soon as it is
 * chosen and the form goes on submitting the URL it gets back, exactly as
 * it did when someone was pasting one in by hand.
 */
class LogoUploadController extends Controller
{
    public function store(StoreLogoRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        try {
            $url = Bus::dispatch(new UploadPartyLogo($company->id, $request->file('logo')));
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->withErrors(['logo' => 'The logo could not be saved. Please try again.']);
        }

        // The enclosing party form has not saved yet. Keep its existing logo
        // intact if the user cancels or the later form submission fails.
        return back()->with('uploaded_logo_url', $url);
    }
}
