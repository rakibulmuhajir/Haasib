<?php

namespace Database\Seeders\Demo;

use App\Models\Company;
use App\Models\User;
use App\Modules\Umrah\Models\Agent;
use App\Services\CompanyContextService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * One signed-in identity per role, on the travel demo company.
 *
 * Everything in the demo data belongs to the owner, which makes the half of
 * the app that exists to keep roles apart untestable: you cannot check that an
 * agent sees only their own refunds, or that operations cannot approve one,
 * from an account that is allowed to do everything. These are the four other
 * seats at the same table.
 *
 * The agent seat is deliberately given to Al-Noor Travels rather than a fresh
 * agent record, because Al-Noor is the only agent carrying an unallocated
 * advance -- and an advance is what a refund is drawn from. An agent login
 * with nothing behind it can request a refund but never see one approved.
 */
class DemoRoleUsersSeeder extends Seeder
{
    private const COMPANY_SLUG = 'demo-babalsalam-travel';

    private const PASSWORD = 'demo-password';

    /** role => [name, username, email] */
    private const SEATS = [
        'manager' => ['Demo Manager', 'demo-manager', 'manager@demo.haasib.app'],
        'accountant' => ['Demo Accountant', 'demo-accountant', 'accountant@demo.haasib.app'],
        'operations' => ['Demo Operations', 'demo-operations', 'operations@demo.haasib.app'],
        'agent' => ['Al-Noor Travels', 'demo-agent', 'agent@demo.haasib.app'],
    ];

    public function run(CompanyContextService $context): void
    {
        $company = Company::where('slug', self::COMPANY_SLUG)->first();

        if (! $company) {
            $this->command?->warn('  '.self::COMPANY_SLUG.' not found; run the travel demo seeder first.');

            return;
        }

        foreach (self::SEATS as $role => [$name, $username, $email]) {
            $user = User::where('email', $email)->first();

            if (! $user) {
                $user = User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make(self::PASSWORD),
                ]);
            }

            // Re-running must leave a usable login behind, not just a row: a
            // password changed by hand during testing is reset, and the
            // verification stamp is re-applied so the seat is not parked
            // behind a verification screen.
            $user->forceFill([
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
            ])->save();

            DB::connection('pgsql')->table('auth.company_user')->updateOrInsert(
                ['company_id' => $company->id, 'user_id' => $user->id],
                [
                    'role' => $role,
                    'is_active' => true,
                    'joined_at' => now(),
                    'left_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );

            // The pivot row makes someone a member; it does not make them
            // anything in particular. What the app actually reads when it asks
            // whether you may approve a refund is a team-scoped Spatie role,
            // and nothing derives one from the other automatically -- there is
            // a whole backfill command (rbac:sync-company-user-roles) that
            // exists because of the gap. Assigning it here means the seat works
            // the moment it is seeded rather than after a second command.
            $context->withContext($company, fn () => $context->assignRole($user, $role));

            $this->command?->info(sprintf('  %-11s %s', $role, $email));
        }

        $this->linkAgentSeat($company);
    }

    /**
     * An agent-role login is only half an identity. What scopes it to one
     * agent's own records is umrah.agents.user_id, so without this the seat
     * signs in and sees an empty list rather than Al-Noor's.
     */
    private function linkAgentSeat(Company $company): void
    {
        $user = User::where('email', self::SEATS['agent'][2])->first();
        $agent = Agent::where('company_id', $company->id)
            ->where('name', 'Al-Noor Travels')
            ->first();

        if (! $user || ! $agent) {
            $this->command?->warn('  agent seat not linked: Al-Noor Travels not found.');

            return;
        }

        $agent->forceFill(['user_id' => $user->id])->save();
        $this->command?->info('  agent seat linked to Al-Noor Travels ('.$agent->id.')');
    }
}
