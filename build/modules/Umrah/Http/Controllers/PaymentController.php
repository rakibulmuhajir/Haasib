<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\ReversePaymentRequest;
use App\Modules\Umrah\Http\Requests\StoreGroupPaymentRequest;
use App\Modules\Umrah\Http\Requests\StorePaymentAllocationRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Models\GroupPayment;
use App\Modules\Umrah\Models\HotelVendor;
use App\Modules\Umrah\Models\VisaVendor;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CompanyCurrencyOptions;
use App\Services\CurrentCompany;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    public function __construct(private readonly UmrahCoreService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_VIEW), 403);
        $isMember = DB::table('auth.company_user')
            ->where('company_id', $company->id)
            ->where('user_id', $request->user()?->id)
            ->where('is_active', true)
            ->value('role') === 'agent';
        $agentId = $isMember
            ? Agent::where('company_id', $company->id)->where('user_id', $request->user()?->id)->where('is_active', true)->value('id')
            : null;

        $query = GroupPayment::where('company_id', $company->id)
            ->with([
                'allocations.group:id,group_number,name',
                'agent:id,name',
                'visaVendor:id,name',
                'transportVendor:id,name',
                'hotelVendor:id,name',
                'account:id,code,name',
                'transaction:id,transaction_number',
            ])
            ->when($isMember, fn ($paymentQuery) => $agentId
                ? $paymentQuery->where('agent_id', $agentId)->where('direction', GroupPayment::DIRECTION_RECEIVED)
                : $paymentQuery->whereRaw('1 = 0'))
            ->when($request->filled('direction') && ! $isMember, fn ($paymentQuery) => $paymentQuery->where('direction', $request->string('direction')))
            ->when($request->filled('search'), function ($paymentQuery) use ($request) {
                $term = $request->string('search')->toString();
                $paymentQuery->where(function ($searchQuery) use ($term) {
                    $searchQuery->where('payment_number', 'ilike', "%{$term}%")
                        ->orWhere('reference', 'ilike', "%{$term}%")
                        ->orWhereHas('allocations.group', fn ($groupQuery) => $groupQuery->where('group_number', 'ilike', "%{$term}%")->orWhere('name', 'ilike', "%{$term}%"));
                });
            });

        $summaryQuery = clone $query;
        $payments = $query->orderByDesc('payment_date')->orderByDesc('created_at')->paginate(25)->withQueryString();
        $allocationGroups = collect($this->service->paymentAllocationOptions($company->id))
            ->when($isMember, fn ($options) => $agentId
                ? $options->where('party_key', 'agent:'.$agentId)
                : collect());

        return Inertia::render('Umrah/Payments/Index', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'payments' => $payments,
            'summary' => [
                'received' => (float) (clone $summaryQuery)->where('status', GroupPayment::STATUS_POSTED)->where('direction', GroupPayment::DIRECTION_RECEIVED)->sum('base_amount'),
                'sent' => $isMember ? 0 : (float) (clone $summaryQuery)->where('status', GroupPayment::STATUS_POSTED)->where('direction', GroupPayment::DIRECTION_SENT)->sum('base_amount'),
            ],
            'directions' => $isMember ? [GroupPayment::DIRECTION_RECEIVED => GroupPayment::DIRECTIONS[GroupPayment::DIRECTION_RECEIVED]] : GroupPayment::DIRECTIONS,
            'filters' => $request->only(['search', 'direction']),
            'allocationGroups' => $allocationGroups->values(),
            'canRecordPayments' => ! $isMember && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_CREATE),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_CREATE), 403);
        $memberAgentId = $this->memberAgentId($company->id, $request);
        $isMember = $memberAgentId !== false;
        $allocationGroups = collect($this->service->paymentAllocationOptions($company->id))
            ->when($isMember, fn ($options) => $memberAgentId
                ? $options->where('party_key', 'agent:'.$memberAgentId)
                : collect());

        return Inertia::render('Umrah/Payments/Create', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'agents' => Agent::where('company_id', $company->id)->where('is_active', true)
                ->when($isMember, fn ($query) => $memberAgentId ? $query->whereKey($memberAgentId) : $query->whereRaw('1 = 0'))
                ->orderBy('name')->get(['id', 'name']),
            'visaVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where(fn ($query) => $query->where('is_active', true)->orWhere('balance', '>', 0))->where('vendor_type', '!=', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'is_active']),
            'transportVendors' => $isMember ? [] : VisaVendor::where('company_id', $company->id)->where(fn ($query) => $query->where('is_active', true)->orWhere('balance', '>', 0))->where('vendor_type', VisaVendor::TYPE_TRANSPORT_PROVIDER)->orderBy('name')->get(['id', 'name', 'is_company_owned', 'is_active']),
            'hotelVendors' => $isMember ? [] : HotelVendor::where('company_id', $company->id)->where(fn ($query) => $query->where('is_active', true)->orWhere('balance', '>', 0))->orderBy('name')->get(['id', 'name', 'is_active']),
            'currencies' => app(CompanyCurrencyOptions::class)->forCompany($company),
            'directions' => $isMember ? [GroupPayment::DIRECTION_RECEIVED => GroupPayment::DIRECTIONS[GroupPayment::DIRECTION_RECEIVED]] : GroupPayment::DIRECTIONS,
            'allocationGroups' => $allocationGroups->values(),
        ]);
    }

    public function store(StoreGroupPaymentRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();
        $memberAgentId = $this->memberAgentId($company->id, $request);
        if ($memberAgentId !== false) {
            abort_if(! $memberAgentId || $data['direction'] !== GroupPayment::DIRECTION_RECEIVED, 403);
            $data['agent_id'] = $memberAgentId;
        }

        $this->service->addPayment($company->id, $data);

        return redirect()->route('umrah.payments.index', ['company' => $company->slug])->with('success', 'Payment recorded successfully.');
    }

    public function allocate(StorePaymentAllocationRequest $request, string $companySlug, string $payment): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = GroupPayment::where('company_id', $company->id)->findOrFail($payment);
        $memberAgentId = $this->memberAgentId($company->id, $request);
        abort_if($memberAgentId !== false && $record->agent_id !== $memberAgentId, 403);

        $this->service->allocatePayment($record, $request->validated());

        return back()->with('success', 'Payment allocated successfully.');
    }

    public function show(Request $request, string $companySlug, string $payment): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_VIEW), 403);
        $record = $this->paymentForUser($company->id, $request, $payment)
            ->load(['agent:id,name', 'visaVendor:id,name', 'transportVendor:id,name', 'hotelVendor:id,name', 'account:id,code,name', 'transaction:id,transaction_number', 'reversalTransaction:id,transaction_number', 'allAllocations.group:id,group_number,name', 'allAllocations.transaction:id,transaction_number', 'allAllocations.reversalTransaction:id,transaction_number']);

        return Inertia::render('Umrah/Payments/Show', [
            'company' => ['name' => $company->name, 'slug' => $company->slug, 'base_currency' => $company->base_currency],
            'payment' => $record,
            'canReverse' => $record->status === GroupPayment::STATUS_POSTED && (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_REVERSE),
        ]);
    }

    public function reverse(ReversePaymentRequest $request, string $companySlug, string $payment): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = GroupPayment::where('company_id', $company->id)->findOrFail($payment);
        $this->service->reversePayment($record, $request->validated('reason'), $request->user()?->id);

        return back()->with('success', 'Payment and its allocations reversed successfully.');
    }

    public function pdf(Request $request, string $companySlug, string $payment)
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_PAYMENT_VIEW), 403);
        $record = $this->paymentForUser($company->id, $request, $payment)
            ->load(['agent:id,name', 'visaVendor:id,name', 'transportVendor:id,name', 'hotelVendor:id,name', 'account:id,code,name', 'allAllocations.group:id,group_number,name']);

        return Pdf::loadView('umrah::payments.receipt', ['company' => $company, 'payment' => $record])
            ->setPaper('a4')->download($record->payment_number.'.pdf');
    }

    private function paymentForUser(string $companyId, Request $request, string $payment): GroupPayment
    {
        $agentId = $this->memberAgentId($companyId, $request);

        return GroupPayment::where('company_id', $companyId)
            ->when($agentId !== false, fn ($query) => $agentId
                ? $query->where('agent_id', $agentId)->where('direction', GroupPayment::DIRECTION_RECEIVED)
                : $query->whereRaw('1 = 0'))
            ->findOrFail($payment);
    }

    private function memberAgentId(string $companyId, Request $request): string|false|null
    {
        $isMember = DB::table('auth.company_user')->where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('role') === 'agent';
        if (! $isMember) {
            return false;
        }

        return Agent::where('company_id', $companyId)->where('user_id', $request->user()?->id)->where('is_active', true)->value('id');
    }
}
