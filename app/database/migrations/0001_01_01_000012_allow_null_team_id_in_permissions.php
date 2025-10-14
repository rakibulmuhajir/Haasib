<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop primary key constraints first
        Schema::table('auth.model_has_roles', function (Blueprint $table) {
            $table->dropPrimary('model_has_roles_pkey');
        });

        Schema::table('auth.model_has_permissions', function (Blueprint $table) {
            $table->dropPrimary('model_has_permissions_permission_model_type_primary');
        });

        // Make team_id nullable in permission tables to support system-wide roles
        Schema::table('auth.model_has_roles', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });

        Schema::table('auth.model_has_permissions', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });

        Schema::table('auth.roles', function (Blueprint $table) {
            $table->uuid('team_id')->nullable()->change();
        });

        // Recreate primary keys (allowing NULL team_id)
        Schema::table('auth.model_has_roles', function (Blueprint $table) {
            $table->primary(['team_id', 'role_id', 'model_id', 'model_type'], 'model_has_roles_pkey');
        });

        Schema::table('auth.model_has_permissions', function (Blueprint $table) {
            $table->primary(['team_id', 'permission_id', 'model_id', 'model_type'], 'model_has_permissions_permission_model_type_primary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Set team_id to not null - set any NULL values to a default UUID first
        $defaultTeamId = '00000000-0000-0000-0000-000000000000';

        DB::table('auth.model_has_roles')->whereNull('team_id')->update(['team_id' => $defaultTeamId]);
        DB::table('auth.model_has_permissions')->whereNull('team_id')->update(['team_id' => $defaultTeamId]);
        DB::table('auth.roles')->whereNull('team_id')->update(['team_id' => $defaultTeamId]);

        Schema::table('auth.model_has_roles', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });

        Schema::table('auth.model_has_permissions', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });

        Schema::table('auth.roles', function (Blueprint $table) {
            $table->uuid('team_id')->nullable(false)->change();
        });
    }
};