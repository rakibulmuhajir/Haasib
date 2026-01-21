<?php

namespace App\Http\Controllers;

use App\Models\Partner;
use App\Models\PartnerTransaction;
use App\Modules\Accounting\Models\Account;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function index(): Response
    {
        $company = app(CurrentCompany::class)->get();

        $partners = Partner::where('company_id', $company->id)
            ->withCount('transactions')
            ->orderBy('name')
            ->get()
            ->map(fn($p) => [
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
                'current_period_withdrawn' => $p->current_period_withdrawn,
                'is_active' => $p->is_active,
                'transactions_count' => $p->transactions_count,
            ]);

        $stats = [
            'total_partners' => $partners->count(),
            'active_partners' => $partners->where('is_active', true)->count(),
            'total_capital' => $partners->sum('net_capital'),
            'total_invested' => $partners->sum('total_invested'),
            'total_withdrawn' => $partners->sum('total_withdrawn'),
        ];

        return Inertia::render('partners/Index', [
            'partners' => $partners,
            'stats' => $stats,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        // Get equity accounts for partner capital
        $equityAccounts = Account::where('company_id', $company->id)
            ->where('account_type', 'equity')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('partners/Create', [
            'equityAccounts' => $equityAccounts,
            'currency' => $company->base_currency ?? 'PKR',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'profit_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'drawing_limit_period' => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'drawing_limit_amount' => ['nullable', 'numeric', 'min:0'],
            'drawing_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'initial_investment' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
        ]);

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
                'drawing_account_id' => $validated['drawing_account_id'] ?? null,
                'total_invested' => 0,
                'total_withdrawn' => 0,
                'current_period_withdrawn' => 0,
                'is_active' => $validated['is_active'] ?? true,
                'created_by_user_id' => $request->user()->id,
            ]);

            // Record initial investment if provided
            if (!empty($validated['initial_investment']) && $validated['initial_investment'] > 0) {
                PartnerTransaction::create([
                    'company_id' => $company->id,
                    'partner_id' => $partner->id,
                    'transaction_date' => now()->toDateString(),
                    'transaction_type' => 'investment',
                    'amount' => $validated['initial_investment'],
                    'description' => 'Initial capital investment',
                    'payment_method' => 'cash',
                    'recorded_by_user_id' => $request->user()->id,
                ]);

                $partner->increment('total_invested', $validated['initial_investment']);
            }

            DB::commit();

            return redirect()->route('partners.index', ['company' => $company->slug])
                ->with('success', 'Partner created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to create partner: ' . $e->getMessage());
        }
    }

    public function show(Request $request, string $company, string $partner): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)
            ->findOrFail($partner);

        // Get recent transactions
        $transactions = PartnerTransaction::where('partner_id', $partner)
            ->orderByDesc('transaction_date')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn($t) => [
                'id' => $t->id,
                'transaction_date' => $t->transaction_date->format('Y-m-d'),
                'transaction_type' => $t->transaction_type,
                'amount' => $t->amount,
                'description' => $t->description,
                'reference' => $t->reference,
                'payment_method' => $t->payment_method,
            ]);

        // Calculate running balance
        $runningBalance = 0;
        $transactionsWithBalance = $transactions->reverse()->map(function ($t) use (&$runningBalance) {
            if ($t['transaction_type'] === 'investment') {
                $runningBalance += $t['amount'];
            } else {
                $runningBalance -= $t['amount'];
            }
            return array_merge($t, ['balance' => $runningBalance]);
        })->reverse()->values();

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
                'net_capital' => $partnerModel->net_capital,
                'remaining_drawing_limit' => $partnerModel->remaining_drawing_limit,
                'current_period_withdrawn' => $partnerModel->current_period_withdrawn,
                'is_active' => $partnerModel->is_active,
            ],
            'transactions' => $transactionsWithBalance,
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    public function edit(Request $request, string $company, string $partner): Response
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)
            ->findOrFail($partner);

        $equityAccounts = Account::where('company_id', $companyModel->id)
            ->where('account_type', 'equity')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('partners/Edit', [
            'partner' => $partnerModel,
            'equityAccounts' => $equityAccounts,
            'currency' => $companyModel->base_currency ?? 'PKR',
        ]);
    }

    public function update(Request $request, string $company, string $partner): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)
            ->findOrFail($partner);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'cnic' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'profit_share_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'drawing_limit_period' => ['required', Rule::in(['none', 'monthly', 'yearly'])],
            'drawing_limit_amount' => ['nullable', 'numeric', 'min:0'],
            'drawing_account_id' => ['nullable', 'uuid', 'exists:acct.accounts,id'],
            'is_active' => ['boolean'],
        ]);

        $partnerModel->update($validated);

        return redirect()->route('partners.show', ['company' => $companyModel->slug, 'partner' => $partner])
            ->with('success', 'Partner updated successfully.');
    }

    public function addInvestment(Request $request, string $company, string $partner): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)
            ->findOrFail($partner);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque'])],
        ]);

        DB::beginTransaction();
        try {
            PartnerTransaction::create([
                'company_id' => $companyModel->id,
                'partner_id' => $partner,
                'transaction_date' => $validated['transaction_date'],
                'transaction_type' => 'investment',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Capital investment',
                'reference' => $validated['reference'] ?? null,
                'payment_method' => $validated['payment_method'],
                'recorded_by_user_id' => $request->user()->id,
            ]);

            $partnerModel->increment('total_invested', $validated['amount']);

            DB::commit();

            return redirect()->back()->with('success', 'Investment recorded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to record investment: ' . $e->getMessage());
        }
    }

    public function addWithdrawal(Request $request, string $company, string $partner): RedirectResponse
    {
        $companyModel = app(CurrentCompany::class)->get();

        $partnerModel = Partner::where('company_id', $companyModel->id)
            ->findOrFail($partner);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'description' => ['nullable', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'cheque'])],
        ]);

        DB::beginTransaction();
        try {
            PartnerTransaction::create([
                'company_id' => $companyModel->id,
                'partner_id' => $partner,
                'transaction_date' => $validated['transaction_date'],
                'transaction_type' => 'withdrawal',
                'amount' => $validated['amount'],
                'description' => $validated['description'] ?? 'Partner withdrawal',
                'reference' => $validated['reference'] ?? null,
                'payment_method' => $validated['payment_method'],
                'recorded_by_user_id' => $request->user()->id,
            ]);

            $partnerModel->increment('total_withdrawn', $validated['amount']);
            $partnerModel->increment('current_period_withdrawn', $validated['amount']);

            DB::commit();

            return redirect()->back()->with('success', 'Withdrawal recorded successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Failed to record withdrawal: ' . $e->getMessage());
        }
    }
}
