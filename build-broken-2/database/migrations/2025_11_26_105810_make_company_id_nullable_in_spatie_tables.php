<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE roles DROP CONSTRAINT IF EXISTS roles_company_id_name_guard_name_unique');
        DB::statement('ALTER TABLE roles ALTER COLUMN company_id DROP NOT NULL');
        DB::statement('ALTER TABLE roles ADD CONSTRAINT roles_company_id_name_guard_name_unique UNIQUE (company_id, name, guard_name)');
        
        DB::statement('ALTER TABLE model_has_roles DROP CONSTRAINT IF EXISTS model_has_roles_pkey');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN company_id DROP NOT NULL');
        
        DB::statement('ALTER TABLE model_has_permissions DROP CONSTRAINT IF EXISTS model_has_permissions_pkey');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN company_id DROP NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DELETE FROM roles WHERE company_id IS NULL');
        DB::statement('DELETE FROM model_has_roles WHERE company_id IS NULL');
        DB::statement('DELETE FROM model_has_permissions WHERE company_id IS NULL');
        
        DB::statement('ALTER TABLE roles ALTER COLUMN company_id SET NOT NULL');
        DB::statement('ALTER TABLE model_has_roles ALTER COLUMN company_id SET NOT NULL');
        DB::statement('ALTER TABLE model_has_permissions ALTER COLUMN company_id SET NOT NULL');
    }
};
