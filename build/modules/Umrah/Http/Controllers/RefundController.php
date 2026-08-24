<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Umrah\Http\Requests\ApproveRefundRequest;
use App\Modules\Umrah\Http\Requests\CancelRefundRequest;
use App\Modules\Umrah\Http\Requests\RejectRefundRequest;
use App\Modules\Umrah\Http\Requests\SettleRefundRequest;
use App\Modules\Umrah\Http\Requests\StoreRefundRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\Refund;
use App\Modules\Umrah\Models\VisaGroup;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
                ->orderByName()->get(['id', 'customer_id']),
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name']),
            'hotelVendors' => $isMember ? [] : HotelVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'allocationGroups' => collect($this->coreService->paymentAllocationOptions($company->id))
                ->when($isMember, fn ($options) => $memberAgentId ? $options->where('party_key', 'agent:'.$memberAgentId) : collect())
                ->values(),
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
            'initial' => $this->initialFromQuery($request, $company->id, $isMember, $memberAgentId),
        ]);
    }

    /**
     * A refund is requested from wherever a person is looking at the money
     * that went wrong -- the agent's page, a group's accounting tab, a
     * vendor's page -- each linking here with the party (and, for a group,
     * the group) as query parameters. Every value is re-validated against
     * this company rather than trusted, and ignored rather than erroring
     * when it does not check out: a stale or hand-edited link should land
     * on a blank form, not a 422.
     *
     * The $isMember narrowing wins over any prefill: an agent user's party
     * is always their own linked agent, never whatever party_type/party_id
     * a query string carries. This is the same rule store() already
     * enforces on submission -- this method only makes the create screen
     * agree with it before the user ever presses submit.
     */
    private function initialFromQuery(Request $request, string $companyId, bool $isMember, string|false|null $memberAgentId): array
    {
        $initial = [];

        if ($isMember) {
            if ($memberAgentId) {
                $initial['party_type'] = Refund::PARTY_AGENT;
                $initial['party_id'] = $memberAgentId;
            }
        } else {
            $partyType = $request->query('party_type');
            $partyId = $request->query('party_id');

            if (is_string($partyType) && $this->isUuid($partyId) && array_key_exists($partyType, Refund::PARTY_TYPES)) {
                $exists = match ($partyType) {
                    Refund::PARTY_AGENT => Agent::where('company_id', $companyId)->whereKey($partyId)->exists(),
                    Refund::PARTY_VISA_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($partyId)->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->exists(),
                    Refund::PARTY_TRANSPORT_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($partyId)->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->exists(),
                    Refund::PARTY_HOTEL_VENDOR => HotelVendor::where('company_id', $companyId)->whereKey($partyId)->exists(),
                    default => false,
                };

                if ($exists) {
                    $initial['party_type'] = $partyType;
                    $initial['party_id'] = $partyId;
                }
            }
        }

        $groupId = $request->query('visa_group_id');
        if ($this->isUuid($groupId)) {
            $group = VisaGroup::where('company_id', $companyId)->find($groupId);

            // Only an agent refund can be linked to a group, and only when
            // the group's agent agrees with whatever party the block above
            // already settled on (nothing, or that same agent).
            if ($group
                && $group->agent_id
                && (! $isMember || $group->agent_id === $memberAgentId)
                && ($initial['party_type'] ?? Refund::PARTY_AGENT) === Refund::PARTY_AGENT
                && ($initial['party_id'] ?? $group->agent_id) === $group->agent_id
            ) {
                $initial['party_type'] = Refund::PARTY_AGENT;
                $initial['party_id'] = $group->agent_id;
                $initial['visa_group_id'] = $group->id;
            }
        }

        return $initial;
    }

    /**
     * These columns are uuid, and Postgres rejects a malformed uuid with a
     * cast error rather than simply not matching. Without this guard a
     * hand-edited link would raise a 500 from inside the query, which is the
     * one thing initialFromQuery() promises not to do.
     */
    private function isUuid(mixed $value): bool
    {
        return is_string($value) && Str::isUuid($value);
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
            ->load(['group:id,group_number,name', 'requestedBy:id,name', 'reviewedBy:id,name', 'cancelledBy:id,name', 'settledBy:id,name']);

        $canSettle = $record->status === Refund::STATUS_ACCEPTED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_APPROVE);

        return Inertia::render('Umrah/Refunds/Show', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'refund' => $this->withPartyName($record),
            'canApprove' => $record->status === Refund::STATUS_REQUESTED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_APPROVE),
            'canCancel' => $record->status === Refund::STATUS_ACCEPTED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_REFUND_CANCEL),
            'canSettle' => $canSettle,
            'settlementAccounts' => $canSettle
                ? Account::where('company_id', $company->id)
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->whereIn('subtype', ['bank', 'cash'])
                    ->orderBy('code')
                    ->get(['id', 'code', 'name'])
                : [],
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

    public function settle(SettleRefundRequest $request, string $companySlug, string $refund): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Refund::where('company_id', $company->id)->findOrFail($refund);
        $this->service->settle($record, $request->validated(), $request->user()?->id);

        return back()->with('success', 'Refund settled.');
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
