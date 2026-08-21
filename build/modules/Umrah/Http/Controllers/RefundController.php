<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\ApproveRefundRequest;
use App\Modules\Umrah\Http\Requests\CancelRefundRequest;
use App\Modules\Umrah\Http\Requests\RejectRefundRequest;
use App\Modules\Umrah\Http\Requests\StoreRefundRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RefundController extends Controller
{
    public function __construct(
        private readonly RefundService $service,
        private readonly UmrahCoreService $coreService,
        private readonly TravelAccessService $access,
    ) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_VIEW), 403);

        $agentId = $this->memberAgentId($company->id, $request);
        $isMember = $agentId !== false;

        $query = Refund::where('company_id', $company->id)
            ->with(['group:id,group_number,name', 'requestedBy:id,name', 'reviewedBy:id,name'])
            ->when($isMember, fn ($refundQuery) => $agentId
                ? $refundQuery->where('party_type', Refund::PARTY_AGENT)->where('party_id', $agentId)
                : $refundQuery->whereRaw('1 = 0'))
            ->when($request->filled('status'), fn ($refundQuery) => $refundQuery->where('status', $request->string('status')));

        $refunds = $query->orderByDesc('requested_at')->orderByDesc('created_at')->paginate(25)->withQueryString();
        $refunds->getCollection()->transform(fn (Refund $refund) => $this->withPartyName($refund));

        return Inertia::render('Umrah/Refunds/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'refunds' => $refunds,
            'statuses' => Refund::STATUSES,
            'filters' => $request->only(['status']),
            'canCreate' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_CREATE),
            'canApprove' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_APPROVE),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_CREATE), 403);
        $memberAgentId = $this->memberAgentId($company->id, $request);
        $isMember = $memberAgentId !== false;

        return Inertia::render('Umrah/Refunds/Create', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'partyTypes' => $isMember ? [Refund::PARTY_AGENT => Refund::PARTY_TYPES[Refund::PARTY_AGENT]] : Refund::PARTY_TYPES,
            'services' => Refund::SERVICES,
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)
                ->when($isMember, fn ($query) => $memberAgentId ? $query->whereKey($memberAgentId) : $query->whereRaw('1 = 0'))
                ->orderBy('name')->get(['id', 'name']),
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name']),
            'hotelVendors' => $isMember ? [] : HotelVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allocationGroups' => collect($this->coreService->paymentAllocationOptions($company->id))
                ->when($isMember, fn ($options) => $memberAgentId ? $options->where('party_key', 'agent:'.$memberAgentId) : collect())
                ->values(),
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
        ]);
    }

    public function store(StoreRefundRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        $memberAgentId = $this->memberAgentId($company->id, $request);
        if ($memberAgentId !== false) {
            abort_if(! $memberAgentId || $data['party_type'] !== Refund::PARTY_AGENT, 403);
            $data['party_id'] = $memberAgentId;
        }

        $this->service->request($company->id, $data, $request->user()?->id);

        return redirect()->route('umrah.refunds.index', ['company' => $company->slug])->with('success', 'Refund requested.');
    }

    public function show(Request $request, string $companySlug, string $refund): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_VIEW), 403);
        $record = $this->refundForUser($company->id, $request, $refund)
            ->load(['group:id,group_number,name', 'settledPayment:id,payment_number', 'requestedBy:id,name', 'reviewedBy:id,name', 'cancelledBy:id,name']);

        return Inertia::render('Umrah/Refunds/Show', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'refund' => $this->withPartyName($record),
            'canApprove' => $record->status === Refund::STATUS_REQUESTED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_APPROVE),
            'canCancel' => $record->status === Refund::STATUS_APPROVED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_CANCEL),
        ]);
    }

    public function approve(ApproveRefundRequest $request, string $companySlug, string $refund): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Refund::where('company_id', $company->id)->findOrFail($refund);
        $this->service->approve($record, $request->validated(), $request->user()?->id);

        return back()->with('success', 'Refund approved.');
    }

    public function reject(RejectRefundRequest $request, string $companySlug, string $refund): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Refund::where('company_id', $company->id)->findOrFail($refund);
        $this->service->reject($record, $request->validated('review_remarks'), $request->user()?->id);

        return back()->with('success', 'Refund rejected.');
    }

    public function cancel(CancelRefundRequest $request, string $companySlug, string $refund): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Refund::where('company_id', $company->id)->findOrFail($refund);
        $this->service->cancel($record, $request->validated('cancellation_reason'), $request->user()?->id);

        return back()->with('success', 'Refund cancelled.');
    }

    private function withPartyName(Refund $refund): Refund
    {
        $refund->setAttribute('party_name', match ($refund->party_type) {
            Refund::PARTY_AGENT => Agent::find($refund->party_id)?->name,
            Refund::PARTY_VISA_VENDOR, Refund::PARTY_TRANSPORT_VENDOR => VisaVendor::find($refund->party_id)?->name,
            Refund::PARTY_HOTEL_VENDOR => HotelVendor::find($refund->party_id)?->name,
            default => null,
        });

        return $refund;
    }

    private function refundForUser(string $companyId, Request $request, string $refund): Refund
    {
        $agentId = $this->memberAgentId($companyId, $request);

        return Refund::where('company_id', $companyId)
            ->when($agentId !== false, fn ($query) => $agentId
                ? $query->where('party_type', Refund::PARTY_AGENT)->where('party_id', $agentId)
                : $query->whereRaw('1 = 0'))
            ->findOrFail($refund);
    }

    /**
     * Refund does not carry an `agent_id` column the way GroupPayment does
     * (its ownership is party_type/party_id, polymorphic across agent and
     * vendor rows), so TravelAccessService::scopeAgentRecords -- which
     * filters on a literal `agent_id` column -- does not fit directly. This
     * mirrors PaymentController::memberAgentId, which is the same lookup
     * TravelAccessService::isAgentMember/linkedAgent perform, kept local so
     * the party_type/party_id scoping above stays readable.
     */
    private function memberAgentId(string $companyId, Request $request): string|false|null
    {
        if (! $this->access->isAgentMember($companyId, $request->user())) {
            return false;
        }

        return $this->access->linkedAgent($companyId, $request->user())?->id;
    }
}
