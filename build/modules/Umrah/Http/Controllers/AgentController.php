<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\DestroyAgentRequest;
use App\Modules\Umrah\Http\Requests\StoreAgentRequest;
use App\Modules\Umrah\Http\Requests\UpdateAgentRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AgentController extends Controller
{
    public function __construct(private UmrahCoreService $service) {}

    public function index(Request $request): Response
    {
        $company = app(CurrentCompany::class)->get();
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
        ]);
    }

    public function create(): Response
    {
        $company = app(CurrentCompany::class)->get();

        return Inertia::render('Umrah/Agents/Create', [
            'company' => $this->companyPayload($company),
            'nextAgentNumber' => $this->service->nextAgentNumber($company->id),
            'countries' => Agent::COUNTRIES,
            'companyUsers' => $this->availableLoginUsers($company->id),
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        Agent::create([
            'company_id' => $company->id,
            'user_id' => $data['user_id'] ?? null,
            'agent_number' => $data['agent_number'] ?: $this->service->nextAgentNumber($company->id),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_active' => true,
        ]);

        return redirect()->route('umrah.agents.index', ['company' => $company->slug])
            ->with('success', 'Agent created successfully.');
    }

    public function show(string $companySlug, string $agent): Response
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);

        $record->load(['groups' => fn ($query) => $query->orderByDesc('created_at')->limit(20)]);

        return Inertia::render('Umrah/Agents/Show', [
            'company' => $this->companyPayload($company),
            'agent' => $record,
        ]);
    }

    public function edit(string $companySlug, string $agent): Response
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);

        return Inertia::render('Umrah/Agents/Edit', [
            'company' => $this->companyPayload($company),
            'agent' => $record,
            'countries' => Agent::COUNTRIES,
            'companyUsers' => $this->availableLoginUsers($company->id, $record->id),
        ]);
    }

    public function update(UpdateAgentRequest $request, string $companySlug, string $agent): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $record = Agent::where('company_id', $company->id)->findOrFail($agent);
        $data = $request->validated();

        $record->update([
            'user_id' => $data['user_id'] ?? null,
            'agent_number' => $data['agent_number'] ?: $record->agent_number,
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? null,
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('umrah.agents.show', ['company' => $company->slug, 'agent' => $record->id])
            ->with('success', 'Agent updated successfully.');
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

    private function availableLoginUsers(string $companyId, ?string $currentAgentId = null): array
    {
        return DB::table('auth.company_user as cu')
            ->join('auth.users as users', 'users.id', '=', 'cu.user_id')
            ->leftJoin('umrah.agents as linked_agents', function ($join) use ($companyId, $currentAgentId) {
                $join->on('linked_agents.user_id', '=', 'users.id')
                    ->where('linked_agents.company_id', '=', $companyId)
                    ->whereNull('linked_agents.deleted_at');

                if ($currentAgentId !== null) {
                    $join->where('linked_agents.id', '!=', $currentAgentId);
                }
            })
            ->where('cu.company_id', $companyId)
            ->where('cu.is_active', true)
            ->whereNull('linked_agents.id')
            ->orderBy('users.name')
            ->get(['users.id', 'users.name', 'users.email', 'cu.role'])
            ->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
            ])
            ->all();
    }
}
