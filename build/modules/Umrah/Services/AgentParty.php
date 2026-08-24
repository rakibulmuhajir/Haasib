<?php

namespace App\Modules\Umrah\Services;

use App\Facades\CompanyContext;
use App\Models\Company;
use App\Modules\Accounting\Models\Customer;
use App\Modules\Umrah\Models\Agent;
use App\Services\CommandBus;
use Illuminate\Support\Str;

/**
 * The one place an agent's party record is created or changed.
 *
 * An agent is a customer with an umrah profile attached, so "the agent's name"
 * is the customer's name and there is nowhere else to put it. Every controller
 * that accepts a name, email or phone for an agent hands them here, and they
 * land on acct.customers -- once.
 */
class AgentParty
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    /**
     * The customer for a new agent: the one that already answers to this email
     * in this company, or a new one typed as an agent.
     *
     * Reuse rather than create, because an email that already names a party in
     * this company names this party. Creating a second row would split one
     * counterparty's balance across two ledgers, which is the exact failure the
     * extension is meant to make impossible.
     */
    public function createFor(Company $company, array $data): Customer
    {
        $existing = $this->findByEmail($company, $data['email'] ?? null);

        if ($existing) {
            $existing->update(['customer_type' => Customer::TYPE_AGENT]);

            return $existing;
        }

        /*
         * Through the bus so customer numbering, auditing and events stay in
         * the one handler that owns them -- but with the permission check
         * skipped. The caller has already been authorised to create an agent,
         * and an agent IS a customer; demanding CUSTOMER_CREATE on top would
         * mean an umrah desk that can add agents cannot add agents.
         *
         * Under withContext because the handler reads its company from ambient
         * context, and an agent can be created where none is set -- a seeder, a
         * test, a console command. The company is not in doubt here; it is the
         * one the agent belongs to, and it is handed over rather than looked up.
         */
        $result = CompanyContext::withContext($company, fn () => $this->bus->dispatch('customer.create', [
            'name' => $data['name'],
            'customer_type' => Customer::TYPE_AGENT,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'base_currency' => $company->base_currency,
        ], null, true));

        return Customer::findOrFail($result['data']['id']);
    }

    /**
     * Push edited party fields onto the customer.
     *
     * Not a sync in the sense of reconciling two copies -- there is only one
     * copy. This is the write that the agent form's name/email/phone fields
     * were always meant to perform.
     */
    public function updateFrom(Agent $agent, array $data): void
    {
        $customer = $agent->customer;

        if (! $customer) {
            return;
        }

        $changes = [];

        // array_key_exists, not isset: clearing an agent's email to null is an
        // edit, and isset() would read it as "not submitted" and keep the old
        // address forever.
        foreach (['name', 'email', 'phone', 'logo_url'] as $field) {
            if (array_key_exists($field, $data)) {
                $changes[$field] = $data[$field];
            }
        }

        if ($changes) {
            $customer->update($changes);
        }
    }

    private function findByEmail(Company $company, ?string $email): ?Customer
    {
        if (! $email) {
            return null;
        }

        return Customer::where('company_id', $company->id)
            ->whereRaw('lower(email) = ?', [Str::lower($email)])
            ->first();
    }
}
