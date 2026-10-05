<?php

namespace App\Http\Controllers;

use App\Http\Requests\PartnerMovementRequest;
use App\Http\Requests\ShareProfitRequest;
use App\Http\Requests\StorePartnerRequest;
use App\Http\Requests\UpdatePartnerRequest;
use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Services\CommandBus;
use App\Services\CurrentCompany;
use App\Services\PartnerLedgerService;
use App\Services\PartnerProfitShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();

        $partners = Partner::where('company_id', $company->id)
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'phone' => $p->phone,
                'email' => $p->email,
                'profit_share_percentage' => $p->profit_share_percentage,
                'drawing_limit_period' => $p->drawing_limit_period,
                'drawing_limit_amount' => $p->drawing_limit_amount,
                'total_invested' => $p->total_invested,
                'total_withdrawn' => $p->total_withdrawn,
                'net_capital' => $p->net_capital,
                'remaining_drawing_limit' => $p->remaining_drawing_limit,
                'current_period_withdrawn' => $p->withdrawnThisPeriod(),
                'is_active' => $p->is_active,
                'transactions_count' => $p->transactions_count,
            ]);

        $stats = [
            'total_partners' => $partners->count(),
            'active_partners' => $partners->where('is_active', true)->count(),
            'total_capital' => $partners->sum('net_capital'),
            'total_invested' => $partners->sum('total_invested'),
            'total_withdrawn' => $partners->sum('total_withdrawn'),
            'profit_share_total' => round((float) $partners->where('is_active', true)->sum('profit_share_percentage'), 2),
        ];

        // The month the "Share profit" button offers (last month unless picked) and what sharing
        // it would do; worked out only when asked for (partial reload with ?month=).
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) $request->query('month'))
            ? (string) $request->query('month')
            : now()->subMonthNoOverflow()->format('Y-m');

        return Inertia::render('partners/Index', [
            'partners' => $partners,
            'stats' => $stats,
            'currency' => $company->base_currency ?? 'PKR',
            'shareMonth' => $month,
            'preview' => Inertia::optional(fn () => app(PartnerProfitShareService::class)->preview($company->id, $month)),
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('partners/Create', [
            'cashAccounts' => $this->cashAccounts($company->id),
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $validated = $request->validated();

        DB::beginTransaction();
        try {
            $partner = Partner::create([
                'company_id' => $company->id,
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'cnic' => $validated['cnic'] ?? null,
                'address' => $validated['address'] ?? null,
                'profit_share_percentage' => $validated['profit_share_percentage'],
                'drawing_limit_period' => $validated['drawing_limit_period'],
                'drawing_limit_amount' => $validated['drawing_limit_amount'] ?? null,
                'total_invested' => 0,
                'total_withdrawn' => 0,
                'current_period_withdrawn' => 0,
                'is_active' => $validated['is_active'] ?? true,
                'created_by_user_id' => $request->user()->id,
            ]);

            // Their Capital and Drawings accounts.
            $ledger = app(PartnerLedgerService::class);
            $ledger->accountsFor($partner);

            if (! empty($validated['initial_investment']) && $validated['initial_investment'] > 0) {
                $ledger->invest(
                    $partner, (float) $validated['initial_investment'], now()->toDateString(), $validated['account_id'],
                    'Initial capital investment', 'page', null, $request->user()->id,
                );
            }

            DB::commit();

            return redirect()->route('partners.index', ['company' => $company->slug])
                ->with('success', 'Partner created successfully.');
        } catch (ValidationException $e) {
            DB::rollBack();

            return redirect()->back()->withInput()->withErrors(['initial_investment' => collect($e->errors())->flatten()->first()]);
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()->back()->with('error', 'Failed to create partner: '.$e->getMessage());
        }
    }

    public function show(Request $request, string $company, string $partner): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)->findOrFail($partner);
        $ledger = app(PartnerLedgerService::class);
        $ledger->accountsFor($partnerModel);
        $partnerModel->refresh();

        // Every movement, oldest first, so the running balance adds up; newest shown first.
        $running = 0.0;
        $all = PartnerTransaction::where('partner_id', $partner)
            ->orderBy('transaction_date')->orderBy('created_at')
            ->get()
            ->map(function ($t) use (&$running) {
                $running += $t->transaction_type === 'withdrawal' ? -(float) $t->amount : (float) $t->amount;

                return [
                    'id' => $t->id,
                    'transaction_date' => $t->transaction_date->format('Y-m-d'),
                    'transaction_type' => $t->transaction_type,
                    'amount' => (float) $t->amount,
                    'description' => $t->description,
                    'reference' => $t->reference,
                    'payment_method' => $t->payment_method,
                    'balance' => round($running, 2),
                ];
            });
        $transactions = $all->reverse()->take(100)->values();

        $capital = $ledger->accountNet($companyModel->id, $partnerModel->capital_account_id, 'credit');
        $drawings = $ledger->accountNet($companyModel->id, $partnerModel->drawing_account_id, 'debit');

        return Inertia::render('partners/Show', [
            'partner' => [
                'id' => $partnerModel->id,
                'name' => $partnerModel->name,
                'phone' => $partnerModel->phone,
                'email' => $partnerModel->email,
                'cnic' => $partnerModel->cnic,
                'address' => $partnerModel->address,
                'profit_share_percentage' => $partnerModel->profit_share_percentage,
                'drawing_limit_period' => $partnerModel->drawing_limit_period,
                'drawing_limit_amount' => $partnerModel->drawing_limit_amount,
                'total_invested' => $partnerModel->total_invested,
                'total_withdrawn' => $partnerModel->total_withdrawn,
                'profit_shares' => round((float) PartnerTransaction::where('partner_id', $partner)->where('transaction_type', 'profit_share')->sum('amount'), 2),
                // From the books: Capital account less Drawings account.
                'net_capital' => round($capital - $drawings, 2),
                'capital_balance' => $capital,
                'drawings_balance' => $drawings,
                'remaining_drawing_limit' => $partnerModel->remaining_drawing_limit,
                'current_period_withdrawn' => $partnerModel->withdrawnThisPeriod(),
                'is_active' => $partnerModel->is_active,
            ],
            'transactions' => $transactions,
            'cashAccounts' => $this->cashAccounts($companyModel->id),
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    public function edit(Request $request, string $company, string $partner): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)->findOrFail($partner);

        return Inertia::render('partners/Edit', [
            'partner' => $partnerModel,
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    public function update(UpdatePartnerRequest $request, string $company, string $partner): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)->findOrFail($partner);
        $partnerModel->update($request->validated());

        return redirect()->route('partners.show', ['company' => $companyModel->slug, 'partner' => $partner])
            ->with('success', 'Partner updated successfully.');
    }

    public function addInvestment(PartnerMovementRequest $request, string $company, string $partner): RedirectResponse
    {
        return $this->move('partner.invest', $request, $partner, 'Investment recorded.');
    }

    public function addWithdrawal(PartnerMovementRequest $request, string $company, string $partner): RedirectResponse
    {
        return $this->move('partner.withdraw', $request, $partner, 'Withdrawal recorded.');
    }

    /** Share a month's profit (the confirm dialog on the partners list). */
    public function shareProfit(ShareProfitRequest $request): RedirectResponse
    {
        try {
            $result = app(CommandBus::class)->dispatch('partner.share_profit', $request->validated(), $request->user());
        } catch (ValidationException $e) {
            return redirect()->back()->with('error', collect($e->errors())->flatten()->first());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Could not share the profit: '.$e->getMessage());
        }

        $data = $result['data'];

        return redirect()->back()->with('success', $data['unchanged'] ? "{$data['month']} was already shared." : "{$data['month']}'s profit shared.");
    }

    private function move(string $command, PartnerMovementRequest $request, string $partner, string $message): RedirectResponse
    {
        try {
            $result = app(CommandBus::class)->dispatch($command, [...$request->validated(), 'partner_id' => $partner], $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', 'Failed to record: '.$e->getMessage());
        }

        $redirect = redirect()->back()->with('success', $message);
        if ($warning = $result['data']['warning'] ?? null) {
            $redirect->with('warning', $warning);
        }

        return $redirect;
    }

    /** Cash and bank accounts a partner pays into or is paid from. */
    private function cashAccounts(string $companyId)
    {
        return Account::where('company_id', $companyId)
            ->whereIn('subtype', ['cash', 'bank'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'subtype']);
    }
}
