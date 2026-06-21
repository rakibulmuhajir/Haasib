<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Http\Requests\StoreAmanatHolderRequest;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Services\AmanatService;
use App\Services\CurrentCompany;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AmanatController extends Controller
{
    public function __construct(
        private AmanatService $amanatService
    ) {}

    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get all amanat holders with their profiles and customers
        $amanatHolders = CustomerProfile::where('company_id', $company->id)
            ->where('is_amanat_holder', true)
            ->with('customer')
            ->orderByDesc('amanat_balance')
            ->get();

        $customers = $amanatHolders
            ->filter(fn (CustomerProfile $profile) => $profile->customer !== null)
            ->map(fn (CustomerProfile $profile) => [
                'id' => $profile->id,
                'customer_id' => $profile->customer_id,
                'customer_name' => $profile->customer?->name ?? 'Unknown customer',
                'customer_phone' => $profile->customer?->phone,
                'cnic' => $profile->cnic,
                'amanat_balance' => (float) $profile->amanat_balance,
                'is_credit_customer' => (bool) $profile->is_credit_customer,
                'relationship' => $profile->relationship,
            ])
            ->values();

        // Get summary
        $summary = $this->amanatService->getAmanatSummary($company->id);

        return Inertia::render('FuelStation/Amanat/Index', [
            'customers' => $customers,
            'summary' => $summary,
        ]);
    }

    public function store(StoreAmanatHolderRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        $customer = DB::transaction(function () use ($company, $data) {
            $customer = Customer::create([
                'company_id' => $company->id,
                'customer_number' => $this->nextCustomerNumber($company->id),
                'name' => trim($data['name']),
                'phone' => $data['phone'] ?? null,
                'base_currency' => strtoupper((string) ($company->base_currency ?: 'PKR')),
                'payment_terms' => 0,
                'is_active' => true,
                'created_by_user_id' => auth()->id(),
            ]);

            CustomerProfile::updateOrCreate(
                [
                    'company_id' => $company->id,
                    'customer_id' => $customer->id,
                ],
                [
                    'is_amanat_holder' => true,
                    'relationship' => $data['relationship'] ?? CustomerProfile::RELATIONSHIP_EXTERNAL,
                    'cnic' => $data['cnic'] ?? null,
                ]
            );

            return $customer;
        });

        return redirect()
            ->route('fuel.amanat.show', ['company' => $company->slug, 'customer' => $customer->id])
            ->with('success', 'Amanat holder added successfully. Record deposits from Daily Close.');
    }

    public function show(Request $request): Response|RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $customerModel = $this->findCompanyCustomer($company->id, (string) $request->route('customer'));

        if (! $customerModel) {
            return redirect()
                ->route('fuel.amanat.index', ['company' => $company->slug])
                ->with('error', 'Amanat holder was not found.');
        }

        // Get or create profile
        $profile = CustomerProfile::getOrCreateForCustomer($company->id, $customerModel->id);

        // Get transaction history
        $transactions = AmanatTransaction::where('company_id', $company->id)
            ->where('customer_id', $customerModel->id)
            ->with(['fuelItem', 'recordedBy'])
            ->orderByDesc('created_at')
            ->paginate(50);

        return Inertia::render('FuelStation/Amanat/Show', [
            'customer' => $customerModel,
            'profile' => $profile,
            'transactions' => $transactions,
        ]);
    }

    public function deposit(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        return redirect()
            ->route('fuel.daily-close.create', ['company' => $company->slug])
            ->with('error', 'Amanat deposits are recorded from Daily Close so station cash has one source of truth.');
    }

    public function withdraw(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        return redirect()
            ->route('fuel.daily-close.create', ['company' => $company->slug])
            ->with('error', 'Amanat withdrawals are recorded from Daily Close so station cash has one source of truth.');
    }

    private function findCompanyCustomer(string $companyId, string $customerId): ?Customer
    {
        if (! Str::isUuid($customerId)) {
            return null;
        }

        return Customer::where('company_id', $companyId)->find($customerId);
    }

    private function nextCustomerNumber(string $companyId): string
    {
        $lastNumber = Customer::where('company_id', $companyId)
            ->whereNotNull('customer_number')
            ->lockForUpdate()
            ->orderByDesc('customer_number')
            ->value('customer_number');

        if ($lastNumber && preg_match('/(\d+)$/', $lastNumber, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        } else {
            $sequence = 1;
        }

        return 'CUST-' . str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
