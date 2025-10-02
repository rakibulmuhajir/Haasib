<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add currency foreign key constraints to companies table
        if (Schema::hasTable('auth.companies')) {
            Schema::table('auth.companies', function (Blueprint $table) {
                // Check if foreign keys exist before adding them
                if (! $this->hasForeignKey($table->getTable(), 'auth_companies_created_by_user_id_foreign')) {
                    $table->foreign('created_by_user_id')->references('id')->on('users')->nullOnDelete();
                }
                if (! $this->hasForeignKey($table->getTable(), 'auth_companies_currency_id_foreign')) {
                    $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
                }
                if (! $this->hasForeignKey($table->getTable(), 'auth_companies_exchange_rate_id_foreign')) {
                    $table->foreign('exchange_rate_id')->references('id')->on('exchange_rates')->nullOnDelete();
                }

                // Check if indexes exist before adding them
                if (! $this->hasIndex($table->getTable(), 'auth_companies_currency_id_index')) {
                    $table->index('currency_id');
                }
                if (! $this->hasIndex($table->getTable(), 'auth_companies_exchange_rate_id_index')) {
                    $table->index('exchange_rate_id');
                }
            });
        }

        // Create company_user pivot table
        if (!Schema::hasTable('auth.company_user')) {
            Schema::create('auth.company_user', function (Blueprint $table) {
                $table->uuid('company_id');
                $table->uuid('user_id');
                $table->uuid('invited_by_user_id')->nullable();
                $table->string('role')->default('member');
                $table->timestamps();

                $table->primary(['company_id', 'user_id']);
                $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('invited_by_user_id')->references('id')->on('users')->onDelete('set null');

                $table->index(['company_id', 'user_id']);
                $table->index('invited_by_user_id');
            });

            // Add check constraint for role values
            DB::statement("alter table auth.company_user add constraint auth_company_user_role_chk check (role in ('owner','admin','accountant','viewer','member'))");
        }

        // Create company_secondary_currencies table for multi-currency support
        if (!Schema::hasTable('auth.company_secondary_currencies')) {
            Schema::create('auth.company_secondary_currencies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('company_id');
                $table->uuid('currency_id');
                $table->uuid('exchange_rate_id')->nullable();
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['company_id', 'currency_id'], 'uq_company_currency');
            });

            // Add foreign key constraints for company_secondary_currencies
            Schema::table('auth.company_secondary_currencies', function (Blueprint $table) {
                $table->foreign('company_id')
                    ->references('id')->on('auth.companies')
                    ->onDelete('cascade');
                $table->foreign('currency_id')
                    ->references('id')->on('currencies')
                    ->onDelete('restrict');
                $table->foreign('exchange_rate_id')
                    ->references('id')->on('exchange_rates')
                    ->onDelete('set null');
            });
        }

        // Backfill currency_id for existing companies based on base_currency
        DB::statement('UPDATE auth.companies SET currency_id = currencies.id FROM currencies WHERE auth.companies.base_currency = currencies.code');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            Schema::dropIfExists('auth.company_secondary_currencies');
            Schema::dropIfExists('auth.company_user');

            if (Schema::hasTable('auth.companies')) {
                // Drop foreign keys and indexes from auth.companies
                Schema::table('auth.companies', function (Blueprint $table) {
                    // It's safer to drop by column name array
                    $table->dropForeign(['created_by_user_id']);
                    $table->dropForeign(['currency_id']);
                    $table->dropForeign(['exchange_rate_id']);

                    // Drop indexes by their implicit names
                    $table->dropIndex('auth_companies_created_by_user_id_index');
                    $table->dropIndex('auth_companies_currency_id_index');
                    $table->dropIndex('auth_companies_exchange_rate_id_index');
                });
            }
        } catch (\Throwable $e) {
            // Ignore errors during rollback
        }
    }

    /**
     * Check if a foreign key exists on a table.
     *
     * @param  string  $table
     * @param  string  $name
     * @return bool
     */
    private function hasForeignKey(string $table, string $name): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM information_schema.table_constraints
            WHERE table_schema = 'auth' AND table_name = ? AND constraint_name = ?
        ", [str_replace('auth.', '', $table), str_replace('auth_', '', $name)]);

        return $result[0]->count > 0;
    }

    /**
     * Check if an index exists on a table.
     *
     * @param  string  $table
     * @param  string  $name
     * @return bool
     */
    private function hasIndex(string $table, string $name): bool
    {
        $result = DB::select("
            SELECT COUNT(*) as count
            FROM pg_indexes
            WHERE schemaname = 'auth' AND tablename = ? AND indexname = ?
        ", [str_replace('auth.', '', $table), $name]);

        return $result[0]->count > 0;
    }
};
