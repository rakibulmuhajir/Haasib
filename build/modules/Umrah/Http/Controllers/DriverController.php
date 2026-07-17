<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreDriverRequest;
use App\Modules\Umrah\Http\Requests\UpdateMasterDataStatusRequest;
use App\Modules\Umrah\Models\Driver;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DriverController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless(request()->user()?->hasCompanyPermission(Permissions::UMRAH_SETTINGS_UPDATE), 403);

        return Inertia::render('Umrah/Settings/Drivers', [
            'company' => $this->companyPayload($company),
            'drivers' => Driver::where('company_id', $company->id)
                ->orderBy('name')
                ->get(['id', 'name', 'phone', 'notes', 'is_active']),
        ]);
    }

    public function store(StoreDriverRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        Driver::create([
            'company_id' => $company->id,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()->with('success', 'Driver added successfully.');
    }

    public function update(StoreDriverRequest $request, string $companySlug, string $driver): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Driver::where('company_id', $company->id)->findOrFail($driver);
        $record->update($request->validated());

        return back()->with('success', 'Driver updated successfully.');
    }

    public function updateStatus(UpdateMasterDataStatusRequest $request, string $companySlug, string $driver): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Driver::where('company_id', $company->id)->findOrFail($driver);
        $active = (bool) $request->validated('is_active');
        if (! $active && $record->transportServices()->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['driver' => 'Reassign this driver\'s active transport services first.']);
        }
        $record->update(['is_active' => $active]);

        return back()->with('success', $active ? 'Driver reactivated successfully.' : 'Driver deactivated successfully.');
    }

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
        ];
    }
}
