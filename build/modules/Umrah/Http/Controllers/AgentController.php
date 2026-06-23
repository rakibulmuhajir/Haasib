<?php

namespace App\Modules\Umrah\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Umrah\Http\Requests\StoreAgentRequest;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Services\UmrahCoreService;
use App\Services\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        ]);
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $company = app(CurrentCompany::class)->get();
        $data = $request->validated();

        Agent::create([
            'company_id' => $company->id,
            'agent_number' => $data['agent_number'] ?: $this->service->nextAgentNumber($company->id),
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'city' => $data['city'] ?? null,
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

    private function companyPayload($company): array
    {
        return [
            'id' => $company->id,
            'name' => $company->name,
            'slug' => $company->slug,
            'base_currency' => $company->base_currency,
        ];
    }
}
