<?php

namespace App\Modules\FuelStation\Http\Controllers;

use App\Constants\Permissions;
use App\Http\Controllers\Controller;
use App\Modules\Accounting\Models\Account;
use App\Modules\Accounting\Models\Customer;
use App\Modules\FuelStation\Http\Requests\StoreAmanatHolderRequest;
use App\Modules\FuelStation\Models\AmanatTransaction;
use App\Modules\FuelStation\Models\CustomerProfile;
use App\Modules\FuelStation\Services\AmanatService;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
        $user = $request->user();
        $data = $request->validated();

        $openingAmount = (float) ($data['opening_amount'] ?? 0);
        $openingKind = $data['opening_kind'] ?? 'holds';
        $openingDate = $data['opening_date'] ?? null;

        if ($openingAmount > 0 && ! ($user?->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE) ?? false)) {
            throw ValidationException::withMessages([
                'opening_amount' => 'You are not allowed to set opening balances.',
            ]);
        }

        $commandBus = app(CommandBus::class);

        // Everything - the holder's own creation included - lives in one transaction: if
        // the opening balance is refused (locked position, missing account, ...), the
        // holder is never created either, so there's no orphaned profile to clean up.
        $customer = DB::transaction(function () use ($company, $data, $user, $openingAmount, $openingKind, $openingDate, $commandBus) {
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

            if ($openingAmount > 0) {
                $section = $openingKind === 'owes' ? 'credit_customers' : 'amanat';

                try {
                    $commandBus->dispatch('opening_balance.set_party', [
                        'section' => $section,
                        'party_id' => $customer->id,
                        'amount' => $openingAmount,
                        'as_of_date' => $openingDate,
                    ], $user);
                } catch (ValidationException $e) {
                    throw $this->remapOpeningError($e);
                }

                if ($openingKind === 'owes') {
                    CustomerProfile::where('company_id', $company->id)
                        ->where('customer_id', $customer->id)
                        ->update(['is_credit_customer' => true]);
                }
            }

            return $customer;
        });

        return redirect()
            ->route('fuel.amanat.show', ['company' => $company->slug, 'customer' => $customer->id])
            ->with('success', 'Amanat holder added successfully. Record deposits from Daily Close.');
    }

    /**
     * opening_balance.set_party's errors are keyed for its own params (amount, as_of_date,
     * party_id, or the section name itself when e.g. the amanat liability account is
     * missing). The dialog's fields are opening_amount/opening_date, so a validation
     * failure has to be re-keyed onto the field the user actually sees. Mirrors
     * CustomerController::remapOpeningError.
     */
    private function remapOpeningError(ValidationException $e): ValidationException
    {
        $remapped = [];
        foreach ($e->errors() as $key => $messages) {
            $target = match ($key) {
                'as_of_date' => 'opening_date',
                'amount', 'amanat', 'credit_customers' => 'opening_amount',
                default => 'opening_amount',
            };
            $remapped[$target] = array_merge($remapped[$target] ?? [], $messages);
        }

        return ValidationException::withMessages($remapped);
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

        $transactions = AmanatTransaction::where('company_id', $company->id)
            ->where('customer_id', $customerModel->id)
            ->select('fuel.amanat_transactions.*')
            ->selectRaw('COALESCE((SELECT t.transaction_date FROM acct.journal_entries je
                JOIN acct.transactions t ON t.id = je.transaction_id
                WHERE je.id = fuel.amanat_transactions.journal_entry_id
                  AND t.company_id = fuel.amanat_transactions.company_id),
                fuel.amanat_transactions.created_at::date) AS transaction_date')
            ->with(['fuelItem', 'recordedBy', 'journalEntry.transaction', 'paymentAccount'])
            ->orderByDesc('transaction_date')
            ->orderByDesc('fuel.amanat_transactions.created_at')
            ->orderByDesc('fuel.amanat_transactions.id')
            ->paginate(50);

        return Inertia::render('FuelStation/Amanat/Show', [
            'customer' => $customerModel,
            'profile' => $profile,
            'transactions' => $transactions,
            'canRecordMovement' => $request->user()->hasCompanyPermission(\App\Constants\Permissions::DAILY_CLOSE_CREATE),
            'opening' => $this->openingFor($customerModel->id, $request->user()),
            'paymentAccounts' => Account::where('company_id', $company->id)
                ->where('is_active', true)->whereNull('deleted_at')
                ->whereIn('subtype', ['cash', 'bank'])
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'subtype']),
        ]);
    }

    /**
     * This holder's line in the opening balances, for the holder page's Opening balance card:
     * what they held with us (amanat) or owed us (credit_customers) on the opening date.
     * Null when the user may not manage opening balances.
     */
    private function openingFor(string $customerId, $user): ?array
    {
        if (! ($user?->hasCompanyPermission(Permissions::OPENING_BALANCE_MANAGE) ?? false)) {
            return null;
        }
        $view = app(CommandBus::class)->dispatch('opening_balance.view', [], $user, true);
        $holds = collect($view['rows']['amanat'] ?? [])->where('customer_id', $customerId)->sum('amount');
        $owes = collect($view['rows']['credit_customers'] ?? [])->where('customer_id', $customerId)->sum('amount');

        return [
            'kind' => $owes > 0 ? 'owes' : 'holds',
            'amount' => round((float) ($owes > 0 ? $owes : $holds), 2),
            'as_of_date' => $view['as_of_date'] ?? null,
            'locked' => ! empty($view['locked_at']),
        ];
    }

    /**
     * Set or change a holder's opening balance after creation -- the same opening_balance.set_party
     * the create dialog uses. Switching holds <-> owes clears the other side, so a holder is never
     * in both sections.
     */
    public function setOpening(\App\Modules\FuelStation\Http\Requests\SetAmanatOpeningRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $customer = $this->findCompanyCustomer($company->id, (string) $request->route('customer'));
        abort_unless($customer, 404);
        $data = $request->validated();
        $bus = app(CommandBus::class);
        $current = $this->openingFor($customer->id, $request->user());
        $target = $data['opening_kind'] === 'owes' ? 'credit_customers' : 'amanat';
        $other = $target === 'amanat' ? 'credit_customers' : 'amanat';

        try {
            DB::transaction(function () use ($bus, $customer, $data, $request, $current, $target, $other, $company) {
                if ($current && $current['amount'] > 0 && ($current['kind'] === 'owes' ? 'credit_customers' : 'amanat') === $other) {
                    $bus->dispatch('opening_balance.set_party', [
                        'section' => $other, 'party_id' => $customer->id, 'amount' => 0,
                        'as_of_date' => $data['opening_date'] ?? null,
                    ], $request->user());
                }
                $bus->dispatch('opening_balance.set_party', [
                    'section' => $target, 'party_id' => $customer->id,
                    'amount' => (float) $data['opening_amount'],
                    'as_of_date' => $data['opening_date'] ?? null,
                ], $request->user());
                if ($target === 'credit_customers' && (float) $data['opening_amount'] > 0) {
                    CustomerProfile::where('company_id', $company->id)->where('customer_id', $customer->id)
                        ->update(['is_credit_customer' => true]);
                }
            });
        } catch (ValidationException $e) {
            throw $this->remapOpeningError($e);
        }

        return back()->with('success', "Opening balance saved for {$customer->name}.");
    }

    public function deposit(\App\Modules\FuelStation\Http\Requests\StoreAmanatMovementRequest $request): RedirectResponse
    {
        return $this->recordMovement($request, 'deposit');
    }

    public function withdraw(\App\Modules\FuelStation\Http\Requests\StoreAmanatMovementRequest $request): RedirectResponse
    {
        return $this->recordMovement($request, 'withdraw');
    }

    /** "Allow borrowing": this holder's amanat may go below zero. See CustomerProfile::canDrawAmanat. */
    public function setBorrowing(\App\Modules\FuelStation\Http\Requests\SetAmanatBorrowingRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $customer = $this->findCompanyCustomer($company->id, (string) $request->route('customer'));
        abort_unless($customer, 404);
        $allow = $request->boolean('allow_amanat_borrowing');
        CustomerProfile::getOrCreateForCustomer($company->id, $customer->id)->update(['allow_amanat_borrowing' => $allow]);

        return back()->with('success', $allow ? "{$customer->name} can now borrow from amanat." : "{$customer->name} can no longer go below zero.");
    }

    private function recordMovement(\App\Modules\FuelStation\Http\Requests\StoreAmanatMovementRequest $request, string $method): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $customer = $this->findCompanyCustomer($company->id, (string) $request->route('customer'));
        abort_unless($customer, 404);
        try {
            app(\App\Services\CommandBus::class)->dispatch('fuel.amanat.movement', $request->validated() + ['customer_id' => $customer->id, 'kind' => $method], $request->user());

            return back()->with('success', 'Amanat movement recorded for the selected business date.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
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

        return 'CUST-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
