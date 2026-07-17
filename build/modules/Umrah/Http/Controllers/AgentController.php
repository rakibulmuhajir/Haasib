<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Constants\Permissions;
use App\Constants\Tables;
use App\Facades\CompanyContext;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Umrah\Http\Requests\DestroyAgentRequest;
use App\Modules\Umrah\Http\Requests\StoreAgentRequest;
use App\Modules\Umrah\Http\Requests\UpdateAgentRequest;
use App\Modules\Umrah\Http\Requests\UpdateAgentVoucherAccessRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_VIEW), 403);
        $search = trim((string) $request->input('search', ''));

        $agents = Agent::where('company_id', $company->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($inner) => $inner
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%")
                ->orWhere('agent_number', 'ilike', "%{$search}%")))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Umrah/Agents/Index', [
            'company' => $this->companyPayload($company),
            'agents' => $agents,
            'filters' => ['search' => $search],
            'canManageAgents' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_UPDATE),
        ]);
    }

    public function create(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_CREATE), 403);

        return Inertia::render('Umrah/Agents/Create', [
            'company' => $this->companyPayload($company),
            'nextAgentNumber' => $this->service->nextAgentNumber($company->id),
            'countries' => Agent::COUNTRIES,
            'canManageLogins' => (bool) $request->user()?->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        DB::transaction(function () use ($company, $data, $request) {
            $userId = null;
            if (! empty($data['login_username'])) {
                $userId = $this->createAgentLogin($company->id, $data['name'], $data['login_username'], $data['password'], $request)->id;
            }

            Agent::create([
                'company_id' => $company->id, 'user_id' => $userId,
                'agent_number' => $data['agent_number'] ?: $this->service->nextAgentNumber($company->id),
                'name' => $data['name'], 'phone' => $data['phone'] ?? null, 'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null, 'country' => $data['country'] ?? null, 'notes' => $data['notes'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'can_create_voucher' => (bool) ($data['can_create_voucher'] ?? true), 'can_approve_voucher' => (bool) ($data['can_approve_voucher'] ?? false),
                'can_edit_group' => (bool) ($data['can_edit_group'] ?? false),
                'can_edit_voucher' => (bool) ($data['can_edit_voucher'] ?? false), 'voucher_cutoff_hours' => (int) ($data['voucher_cutoff_hours'] ?? 6), 'is_active' => true,
            ]);
        });

        return redirect()->route('umrah.agents.index', ['company' => $company->slug])
            ->with('success', 'Agent created successfully.');
    }

    public function quickStore(StoreAgentRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        $agent = Agent::create([
            'company_id' => $company->id,
            'user_id' => $data['user_id'] ?? null,
            'agent_number' => $data['agent_number'] ?: $this->service->nextAgentNumber($company->id),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return back()
            ->with('success', "Agent {$agent->name} created successfully.")
            ->with('created_agent_id', $agent->id);
    }

    public function show(Request $request, string $companySlug, string $agent): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_VIEW), 403);
        $record = Agent::where('company_id', $company->id)->with('user:id,username')->findOrFail($agent);

        $record->load(['groups' => fn ($query) => $query->orderByDesc('created_at')->limit(20)]);

        return Inertia::render('Umrah/Agents/Show', [
            'company' => $this->companyPayload($company),
            'agent' => $record,
            'canManageAgents' => (bool) $request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_UPDATE),
        ]);
    }

    public function edit(Request $request, string $companySlug, string $agent): Response
    {
        $company = app(CurrentCompany::class)->get();
        abort_unless($request->user()?->hasCompanyPermission(Permissions::UMRAH_AGENT_UPDATE), 403);
        $record = Agent::where('company_id', $company->id)->with('user:id,username')->findOrFail($agent);

        return Inertia::render('Umrah/Agents/Edit', [
            'company' => $this->companyPayload($company),
            'agent' => $record,
            'countries' => Agent::COUNTRIES,
            'canManageLogins' => (bool) $request->user()?->hasCompanyPermission(Permissions::COMPANY_MANAGE_USERS),
        ]);
    }

    public function update(UpdateAgentRequest $request, string $companySlug, string $agent): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);
        $data = $request->validated();

        DB::transaction(function () use ($company, $record, $data, $request) {
            $user = $record->user;
            if ($user && ! empty($data['login_username'])) {
                $credentials = ['name' => $data['name'], 'username' => Str::lower($data['login_username'])];
                if (! empty($data['password'])) {
                    $credentials['password'] = $data['password'];
                }
                $user->update($credentials);
            } elseif (! $user && ! empty($data['login_username'])) {
                if (empty($data['password'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['password' => 'A password is required when creating agent login access.']);
                }
                $user = $this->createAgentLogin($company->id, $data['name'], $data['login_username'], $data['password'], $request);
            }

            $record->update([
                'user_id' => $user?->id,
                'agent_number' => $data['agent_number'] ?: $record->agent_number,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'city' => $data['city'] ?? null,
                'country' => $data['country'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->route('umrah.agents.show', ['company' => $company->slug, 'agent' => $record->id])
            ->with('success', 'Agent updated successfully.');
    }

    public function updateVoucherAccess(UpdateAgentVoucherAccessRequest $request, string $companySlug, string $agent): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);
        $record->update($request->validated());

        return back()->with('success', 'Agent voucher access updated successfully.');
    }

    public function destroy(DestroyAgentRequest $request, string $companySlug, string $agent): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);

        $record->update(['is_active' => false]);
        $record->delete();

        return redirect()->route('umrah.agents.index', ['company' => $company->slug])
            ->with('success', 'Agent removed successfully.');
    }

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
        ];
    }

    private function createAgentLogin(string $companyId, string $name, string $loginUsername, string $password, Request $request): User
    {
        $username = Str::lower($loginUsername);
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => sprintf('agent+%s.%s@internal.haasib', $username, substr(str_replace('-', '', $companyId), 0, 8)),
            'password' => $password,
        ]);
        DB::table(Tables::COMPANY_USER)->insert([
            'company_id' => $companyId, 'user_id' => $user->id, 'role' => 'agent',
            'invited_by_user_id' => $request->user()?->id, 'joined_at' => now(), 'is_active' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        CompanyContext::assignRole($user, 'agent');

        return $user;
    }
}
