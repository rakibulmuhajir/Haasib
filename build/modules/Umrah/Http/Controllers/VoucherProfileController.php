<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Commands\SaveVoucherProfile;
use App\Modules\Umrah\Http\Requests\SaveVoucherProfileRequest;
use App\Modules\Umrah\Services\VoucherPrintProfiles;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Inertia\Inertia;
use Inertia\Response;

class VoucherProfileController extends Controller
{
    public function index(Request $request, VoucherPrintProfiles $profiles): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/Voucher', [
            'company' => $company->only(['name', 'slug']),
            'profiles' => $profiles->catalog($company, allAgents: true),
            'target' => $request->string('target')->toString(),
        ]);
    }

    public function update(SaveVoucherProfileRequest $request): RedirectResponse
    {
        try {
            Bus::dispatch(new SaveVoucherProfile(app(CurrentCompany::class)->get()->id, $request->validated()));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            report($exception);

            return back()->with('error', 'Voucher settings could not be saved. Please try again.');
        }

        return back()->with('success', 'Voucher defaults saved. Existing vouchers are unchanged.');
    }
}
