<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/*
 * An agent becomes an extension of a customer rather than a party beside one.
 *
 * 2026_08_24_000001 added umrah.agents.customer_id and nothing ever wrote it.
 * The ticket booking form reads it to derive the buyer from the agent, so with
 * every agent holding null the agent-linked booking path could not be satisfied
 * at all: the form disabled the Buyer field, then the request rejected the
 * submission with "The customer id field is required."
 *
 * Making the column NOT NULL and UNIQUE is the fix, and it is also the reason
 * the four duplicated columns can go. name, phone, email and logo_url exist on
 * acct.customers with the same meaning and the same width; keeping a second
 * copy on the agent means the two can disagree, and the only defence against
 * that is remembering to write both. A constraint is a better defence than a
 * habit.
 *
 * city and country stay. They are NOT duplicates: customers hold an address as
 * a billing_address jsonb keyed by ISO-2 country code, while an agent's country
 * is one of six display names curated for the umrah desk. Folding one into the
 * other would silently change what the value means. That is what an extension
 * table is for -- the facts that are specific to this side of the business.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->backfillCustomers();

        /*
         * Dropped by qualified name rather than through the Blueprint, which
         * emits an unqualified `drop index "umrah_agents_..."`. An index lives
         * in its table's schema, and umrah is not on the search_path, so the
         * unqualified form looks in public and reports the index missing.
         * 2026_08_24_000001 hit the same wall and took the same way round it.
         *
         * The first index goes because the unique below indexes the same column
         * and says something the index did not: no two agents may share a
         * customer. The second goes with the column it indexed.
         */
        DB::statement('DROP INDEX IF EXISTS umrah.umrah_agents_company_id_customer_id_index');
        DB::statement('DROP INDEX IF EXISTS umrah.umrah_agents_company_id_name_index');

        DB::statement('ALTER TABLE umrah.agents ALTER COLUMN customer_id SET NOT NULL');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->unique('customer_id');
            $table->dropColumn(['name', 'phone', 'email', 'logo_url']);
        });
    }

    /**
     * Give every agent a customer before the column is made NOT NULL, or the
     * ALTER fails on the first existing row.
     *
     * An agent whose email already matches a customer in the same company is
     * linked to that customer rather than given a second one -- the whole point
     * of the change is that a party is stored once, and creating a duplicate
     * here would violate it in the same migration that enforces it.
     */
    private function backfillCustomers(): void
    {
        $agents = DB::table('umrah.agents')
            ->whereNull('customer_id')
            ->orderBy('company_id')
            ->get(['id', 'company_id', 'name', 'email', 'phone', 'logo_url', 'notes', 'created_at']);

        if ($agents->isEmpty()) {
            return;
        }

        $currencies = DB::table('auth.companies')->pluck('base_currency', 'id');

        foreach ($agents as $agent) {
            $customerId = null;

            if (! empty($agent->email)) {
                $customerId = DB::table('acct.customers')
                    ->where('company_id', $agent->company_id)
                    ->whereRaw('lower(email) = ?', [Str::lower($agent->email)])
                    ->whereNull('deleted_at')
                    ->value('id');
            }

            if ($customerId) {
                // An existing party that turns out to be an agent is an agent.
                DB::table('acct.customers')
                    ->where('id', $customerId)
                    ->update(['customer_type' => 'agent']);
            } else {
                $customerId = (string) Str::uuid();

                DB::table('acct.customers')->insert([
                    'id' => $customerId,
                    'company_id' => $agent->company_id,
                    'customer_number' => $this->nextCustomerNumber($agent->company_id),
                    'name' => $agent->name,
                    'customer_type' => 'agent',
                    'email' => $agent->email,
                    'phone' => $agent->phone,
                    'logo_url' => $agent->logo_url,
                    'base_currency' => $currencies[$agent->company_id] ?? 'PKR',
                    'payment_terms' => 30,
                    'is_active' => true,
                    // The party has existed since the agent did, not since this
                    // migration ran; ageing reports read created_at.
                    'created_at' => $agent->created_at,
                    'updated_at' => now(),
                ]);
            }

            DB::table('umrah.agents')
                ->where('id', $agent->id)
                ->update(['customer_id' => $customerId]);
        }
    }

    /**
     * The same CUST-00001 shape Customer\CreateAction generates. Read fresh each
     * time rather than counted once up front, because the rows this loop
     * inserts have to be counted too.
     */
    private function nextCustomerNumber(string $companyId): string
    {
        $last = DB::table('acct.customers')
            ->where('company_id', $companyId)
            ->whereNotNull('customer_number')
            ->orderByDesc('customer_number')
            ->value('customer_number');

        $sequence = ($last && preg_match('/(\d+)$/', $last, $m)) ? ((int) $m[1]) + 1 : 1;

        return 'CUST-'.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }

    public function down(): void
    {
        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_url', 500)->nullable();
        });

        // Put the party facts back where they were, so the old code can read
        // them, before the column stops being guaranteed.
        DB::statement('
            UPDATE umrah.agents a
               SET name = c.name, phone = c.phone, email = c.email, logo_url = c.logo_url
              FROM acct.customers c
             WHERE c.id = a.customer_id
        ');

        DB::statement("UPDATE umrah.agents SET name = '' WHERE name IS NULL");
        DB::statement('ALTER TABLE umrah.agents ALTER COLUMN name SET NOT NULL');
        DB::statement('ALTER TABLE umrah.agents ALTER COLUMN customer_id DROP NOT NULL');

        Schema::table('umrah.agents', function (Blueprint $table) {
            $table->dropUnique(['customer_id']);
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'name']);
        });
    }
};
