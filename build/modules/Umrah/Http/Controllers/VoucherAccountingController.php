<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\GroupAccountingService;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VoucherAccountingController extends Controller
{
    public function __construct(private readonly GroupAccountingService $accounting) {}

    public function show(Request $request, string $companySlug, string $voucher): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_ACCOUNTING_VIEW), 403);
        $record = Voucher::where('company_id', $company->id)->findOrFail($voucher);

        return Inertia::render('Umrah/Vouchers/Accounting', [
            'company' => [
                'name' => $company->name,
                'slug' => $company->slug,
                'base_currency' => $company->base_currency,
            ],
            ...$this->accounting->voucherSummary($record),
            'canManageGroupAccounting' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_GROUP_ACCOUNTING_UPDATE),
            'canEditVoucher' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_VOUCHER_UPDATE),
        ]);
    }
}
