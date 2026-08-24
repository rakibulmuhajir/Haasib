<?php

namespace App\Modules\Umrah\Dashboard\Widgets;

use App\Constants\Permissions;
use App\Dashboard\DashboardWidget;
use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Modules\Umrah\Services\TravelAccessService;

/**
 * "Agents with money outstanding" — agents whose running balance is not zero.
 */
class AgentBalancesWidget implements DashboardWidget
{
    public function key(): string
    {
        return 'umrah.agent_balances';
    }

    public function title(): string
    {
        return 'Agents with money outstanding';
    }

    public function description(): string
    {
        return 'Agents ranked by balance owed.';
    }

    public function permission(): ?string
    {
        return Permissions::UMRAH_AGENT_VIEW;
    }

    public function defaultSpan(): int
    {
        return 6;
    }

    public function minSpan(): int
    {
        return 6;
    }

    public function resolve(Company $company, User $user, array $options): array
    {
        $limit = (int) ($options['limit'] ?? 8);
        $access = app(TravelAccessService::class);

        $query = Agent::where('company_id', $company->id)->where('balance', '<>', 0);

        // Agent::agent_id doesn't exist — an agent viewing their own balance is
        // scoped by primary key, not by the agent_id column scopeAgentRecords()
        // assumes other Umrah tables have.
        if ($access->isAgentMember($company->id, $user)) {
            $agentId = $access->linkedAgent($company->id, $user)?->id;
            $query = $agentId ? $query->whereKey($agentId) : $query->whereRaw('1 = 0');
        }

        $agents = $query
            ->orderByDesc('balance')
            ->limit($limit)
            ->get(['id', 'customer_id', 'agent_number', 'balance', 'total_receivable', 'total_paid']);

        return [
            'rows' => $agents->map(fn (Agent $agent): array => [
                'id' => $agent->id,
                'agent_number' => $agent->agent_number,
                'name' => $agent->name,
                'balance' => (float) $agent->balance,
                'total_receivable' => (float) $agent->total_receivable,
                'total_paid' => (float) $agent->total_paid,
            ])->values()->all(),
            'currency' => $company->base_currency,
        ];
    }
}
