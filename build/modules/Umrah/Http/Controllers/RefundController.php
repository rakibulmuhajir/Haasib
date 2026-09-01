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
use App\Modules\Umrah\Models\Voucher;
use App\Modules\Umrah\Services\RefundService;
use App\Modules\Umrah\Services\TravelAccessService;
use App\Modules\Umrah\Services\UmrahCoreService;
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
            // Every service, so the form can label one already stored, plus
            // what each kind of party may actually choose from.
            'services' => Refund::SERVICES,
            'servicesByParty' => collect(array_keys(Refund::PARTY_TYPES))
                ->mapWithKeys(fn (string $party) => [$party => Refund::servicesFor($party)])
                ->all(),
            'reasonsByParty' => collect(array_keys(Refund::PARTY_TYPES))
                ->mapWithKeys(fn (string $party) => [$party => Refund::reasonsFor($party)])
                ->all(),
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)
                ->when($isMember, fn ($query) => $memberAgentId ? $query->whereKey($memberAgentId) : $query->whereRaw('1 = 0'))
                ->orderByName()->get(['id', 'customer_id']),
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where('is_active', true)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->orderByName()->get(['id', 'vendor_id']),
            'hotelVendors' => $isMember ? [] : HotelVendor::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'refundGroups' => $this->refundGroupOptions($company->id, $isMember, $memberAgentId),
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
    /**
     * The agent's groups a refund can be attached to.
     *
     * This used to borrow the payment allocation options, which only carry
     * a group while it still owes money -- that list answers "where can
     * this receipt be applied?". A refund asks the opposite question and is
     * usually raised after the agent has paid, so the group being refunded
     * was precisely the one missing from the picker.
     *
     * has_transport and has_hotel let the form narrow to the groups that
     * actually bought the service being refunded. Tickets are absent on
     * purpose: a booking is not attached to a group at all, so there is no
     * group for a ticket refund to name.
     */
    private function refundGroupOptions(string $companyId, bool $isMember, ?string $memberAgentId): array
    {
        return VisaGroup::where('company_id', $companyId)
            ->whereNotNull('agent_id')
            ->where('status', '!=', VisaGroup::STATUS_CANCELLED)
            ->when($isMember, fn ($query) => $memberAgentId ? $query->where('agent_id', $memberAgentId) : $query->whereRaw('1 = 0'))
            ->with([
                'transportItems:id,visa_group_id,transport_vendor_id',
                'vouchers:id,visa_group_id,status,superseded_at,hotel_stays',
            ])
            ->orderByDesc('created_at')
            ->get([
                'id', 'agent_id', 'vendor_id', 'mandatory_transport_vendor_id',
                'group_number', 'name', 'transport_mode', 'passenger_count',
                'visa_sale_amount', 'visa_cost_amount', 'transport_amount', 'transport_cost_amount',
                'hotel_amount', 'hotel_cost_amount',
                'standard_bus_retail_amount', 'standard_bus_cost_amount',
                'standard_bus_billable_passenger_count',
            ])
            ->map(fn (VisaGroup $group) => [
                'id' => $group->id,
                'agent_id' => $group->agent_id,
                'group_number' => $group->group_number,
                'name' => $group->name,
                // Which suppliers billed this trip, so a credit from one of
                // them is only offered the trips it actually charged.
                'vendor_ids' => collect([
                    $group->vendor_id,
                    $group->mandatory_transport_vendor_id,
                    ...$group->transportItems->pluck('transport_vendor_id')->all(),
                    ...$group->vouchers
                        ->where('status', Voucher::STATUS_APPROVED)
                        ->whereNull('superseded_at')
                        ->flatMap(fn (Voucher $voucher) => collect($voucher->hotel_stays)->pluck('hotel_vendor_id'))
                        ->all(),
                ])->filter()->unique()->values()->all(),
                'has_visa' => (float) $group->visa_sale_amount > 0,
                'has_transport' => $group->transport_mode !== VisaGroup::TRANSPORT_NONE,
                'has_hotel' => (float) $group->hotel_amount > 0,
                /*
                 * Both sides of every service, because the two refunds are
                 * asking about different money. An agent is refunded out of
                 * what they were charged; a supplier credits us out of what
                 * they charged us. Sending one figure would have made the
                 * form right for whoever it was written for and quietly
                 * wrong for the other.
                 */
                'charged' => [
                    'sale' => [
                        'visa' => round((float) $group->visa_sale_amount, 2),
                        'transport' => round((float) $group->transport_amount, 2),
                        'hotel' => round((float) $group->hotel_amount, 2),
                    ],
                    'cost' => [
                        'visa' => round((float) $group->visa_cost_amount, 2),
                        'transport' => round((float) $group->transport_cost_amount, 2),
                        'hotel' => round((float) $group->hotel_cost_amount, 2),
                    ],
                ],
                'passenger_count' => (int) $group->passenger_count,
                // Only a standard bus has a per-seat rate to work back from.
                // A specialized group is priced per vehicle or per journey,
                // so there is no per-head figure to offer.
                'per_passenger' => $group->transport_mode === VisaGroup::TRANSPORT_STANDARD_BUS
                    ? [
                        'sale' => round((float) $group->standard_bus_retail_amount, 2),
                        'cost' => round((float) $group->standard_bus_cost_amount, 2),
                        'count' => (int) $group->standard_bus_billable_passenger_count,
                    ]
                    : null,
            ])->values()->all();
    }

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
                    Refund::PARTY_VISA_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($partyId)->where('service_type', '!=', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->exists(),
                    Refund::PARTY_TRANSPORT_VENDOR => VisaVendor::where('company_id', $companyId)->whereKey($partyId)->where('service_type', VisaVendor::SERVICE_TRANSPORT_PROVIDER)->exists(),
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

            /*
             * The group carries through for either side now. An agent
             * refund draws on what that agent paid against the group; a
             * supplier credit lowers what the group cost. What must hold is
             * that the party named actually has something to do with the
             * group -- its agent, or one of the suppliers who billed it.
             */
            $billedBy = $group ? collect([
                $group->vendor_id,
                $group->mandatory_transport_vendor_id,
                ...$group->transportItems()->pluck('transport_vendor_id')->all(),
            ])->filter()->all() : [];

            $partyType = $initial['party_type'] ?? Refund::PARTY_AGENT;
            $partyId = $initial['party_id'] ?? $group?->agent_id;

            $belongs = $partyType === Refund::PARTY_AGENT
                ? ($group?->agent_id && $partyId === $group->agent_id && (! $isMember || $group->agent_id === $memberAgentId))
                : (! $isMember && in_array($partyId, $billedBy, true));

            if ($group && $belongs) {
                $initial['party_type'] = $partyType;
                $initial['party_id'] = $partyId;
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
