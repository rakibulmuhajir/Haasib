<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS auth');

        // Skip creation if the table already exists in the auth schema
        $exists = DB::selectOne("select to_regclass('auth.companies') as reg")?->reg;
        if (! $exists) {
            Schema::create('auth.companies', function (Blueprint $t) {
                $t->uuid('id')->primary();
                $t->uuid('created_by_user_id')->nullable()->index();
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('base_currency', 3)->default('AED');
                $t->uuid('currency_id')->nullable();
                $t->uuid('exchange_rate_id')->nullable();
                $t->string('language', 5)->default('en');
                $t->string('locale', 10)->default('en_AE');
                $t->jsonb('settings')->nullable();
                $t->boolean('is_active')->default(true)->index();
                $t->timestamps();
            });

            // Add foreign key constraint for created_by_user_id only
            // Other foreign keys will be added in a separate migration after dependent tables are created
            Schema::table('auth.companies', function (Blueprint $t) {
                $t->foreign('created_by_user_id')
                    ->references('id')->on('users')
                    ->nullOnDelete();
                // Index is already defined in the table creation
            });

            // Note: company_secondary_currencies table will be created in a separate migration
            // after currencies and exchange_rates tables are created
        }
    }

    public function down(): void
    {
        try {
            Schema::dropIfExists('auth.companies');
        } catch (\Throwable $e) {
            // ignore
        }
    }
};
