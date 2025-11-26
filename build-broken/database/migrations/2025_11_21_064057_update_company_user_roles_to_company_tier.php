<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update existing role names to match company-tier roles
        $roleMapping = [
            'owner' => 'company_owner',
            'admin' => 'company_admin', 
            'member' => 'accounting_operator',
            'viewer' => 'accounting_viewer'
        ];

        foreach ($roleMapping as $oldRole => $newRole) {
            DB::table('auth.company_user')
                ->where('role', $oldRole)
                ->update(['role' => $newRole]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert role names back to original
        $reverseRoleMapping = [
            'company_owner' => 'owner',
            'company_admin' => 'admin',
            'accounting_operator' => 'member', 
            'accounting_viewer' => 'viewer'
        ];

        foreach ($reverseRoleMapping as $newRole => $oldRole) {
            DB::table('auth.company_user')
                ->where('role', $newRole)
                ->update(['role' => $oldRole]);
        }

        // Remove any portal roles that were created
        DB::table('auth.company_user')
            ->whereIn('role', ['portal_customer', 'portal_vendor'])
            ->delete();
    }
};
