<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $teams = config('permission.teams');

        if (! $teams) {
            throw new \Exception('Teams feature must be enabled in config/permission.php before running this migration.');
        }

        // Add company_id to roles table
        if (! Schema::hasColumn($tableNames['roles'], $columnNames['team_foreign_key'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
                $table->uuid($columnNames['team_foreign_key'])->nullable()->after('id');
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            });

            // Drop old unique constraint and create new one with team_foreign_key
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
                $table->dropUnique(['name', 'guard_name']);
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            });
        }

        // Add company_id to model_has_roles table
        if (! Schema::hasColumn($tableNames['model_has_roles'], $columnNames['team_foreign_key'])) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
                $table->uuid($columnNames['team_foreign_key'])->nullable()->after('role_id');
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');
            });

            // Recreate primary key with team_foreign_key
            $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
            $modelMorphKey = $columnNames['model_morph_key'];
            
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($pivotRole, $modelMorphKey) {
                $table->dropPrimary([$pivotRole, $modelMorphKey, 'model_type']);
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames, $pivotRole, $modelMorphKey) {
                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotRole, $modelMorphKey, 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
            });
        }

        // Add company_id to model_has_permissions table
        if (! Schema::hasColumn($tableNames['model_has_permissions'], $columnNames['team_foreign_key'])) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
                $table->uuid($columnNames['team_foreign_key'])->nullable()->after('permission_id');
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');
            });

            // Recreate primary key with team_foreign_key
            $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
            $modelMorphKey = $columnNames['model_morph_key'];
            
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($pivotPermission, $modelMorphKey) {
                $table->dropPrimary([$pivotPermission, $modelMorphKey, 'model_type']);
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames, $pivotPermission, $modelMorphKey) {
                $table->primary(
                    [$columnNames['team_foreign_key'], $pivotPermission, $modelMorphKey, 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
            });
        }

        // Clear cached permissions
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        // Remove company_id from roles
        if (Schema::hasColumn($tableNames['roles'], $columnNames['team_foreign_key'])) {
            Schema::table($tableNames['roles'], function (Blueprint $table) use ($columnNames) {
                $table->dropUnique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
                $table->dropIndex('roles_team_foreign_key_index');
                $table->dropColumn($columnNames['team_foreign_key']);
            });

            Schema::table($tableNames['roles'], function (Blueprint $table) {
                $table->unique(['name', 'guard_name']);
            });
        }

        // Remove company_id from model_has_roles
        if (Schema::hasColumn($tableNames['model_has_roles'], $columnNames['team_foreign_key'])) {
            $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
            $modelMorphKey = $columnNames['model_morph_key'];

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames, $pivotRole, $modelMorphKey) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($columnNames) {
                $table->dropIndex('model_has_roles_team_foreign_key_index');
                $table->dropColumn($columnNames['team_foreign_key']);
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($pivotRole, $modelMorphKey) {
                $table->primary([$pivotRole, $modelMorphKey, 'model_type'], 'model_has_roles_role_model_type_primary');
            });
        }

        // Remove company_id from model_has_permissions
        if (Schema::hasColumn($tableNames['model_has_permissions'], $columnNames['team_foreign_key'])) {
            $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';
            $modelMorphKey = $columnNames['model_morph_key'];

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames, $pivotPermission, $modelMorphKey) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($columnNames) {
                $table->dropIndex('model_has_permissions_team_foreign_key_index');
                $table->dropColumn($columnNames['team_foreign_key']);
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($pivotPermission, $modelMorphKey) {
                $table->primary([$pivotPermission, $modelMorphKey, 'model_type'], 'model_has_permissions_permission_model_type_primary');
            });
        }

        // Clear cached permissions
        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
