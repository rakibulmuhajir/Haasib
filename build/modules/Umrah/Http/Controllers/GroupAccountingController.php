<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\UpdateGroupAccountingRequest;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\GroupAccountingService;
use App\Modules\Umrah\Services\TravelChangeLogger;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GroupAccountingController extends Controller
{
    public function __construct(
        private readonly GroupAccountingService $accounting,
        private readonly TravelChangeLogger $changeLogger,
    ) {}

    public function show(Request $request, string $companySlug, string $group): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_VIEW), 403);
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);

        return Inertia::render('Umrah/Groups/Accounting', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            ...$this->accounting->summary($record),
            'vendors' => VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderByDesc('is_default')->orderBy('name')->get(['id', 'name', 'is_default', 'provides_mandatory_transport', 'mandatory_transport_vendor_id']),
            'transportVendors' => VisaVendor::where('company_id', $company->id)->where('is_active', true)->where(fn ($query) => $query->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orWhere('provides_mandatory_transport', true))->orderBy('name')->get(['id', 'name', 'is_company_owned', 'provides_mandatory_transport']),
            'canUpdate' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_UPDATE),
        ]);
    }

    public function update(UpdateGroupAccountingRequest $request, string $companySlug, string $group): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = VisaGroup::where('company_id', $company->id)->findOrFail($group);
        $before = $record->only(['vendor_id', 'mandatory_transport_vendor_id', 'visa_sale_amount', 'transport_amount', 'discount_amount', 'total_receivable', 'balance', 'profit']);
        $updated = $this->accounting->update($record, $request->validated());
        $after = $updated->only(array_keys($before));
        $this->changeLogger->log($request, $updated, 'visa_group', 'accounting_updated', $before, $after, $request->validated('reason'));

        return back()->with('success', 'Group accounting updated successfully.');
    }
}
