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
        // Only add constraints if the referenced tables have data
        $currencyCount = DB::select("SELECT COUNT(*) as count FROM public.currencies")[0]->count;
        $exchangeRateCount = DB::select("SELECT COUNT(*) as count FROM public.exchange_rates")[0]->count;

        // Clear invalid currency_id and exchange_rate_id values that don't exist in reference tables
        if ($currencyCount > 0) {
            DB::statement("UPDATE auth.companies SET currency_id = NULL WHERE currency_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM public.currencies WHERE id = auth.companies.currency_id)");
        } else {
            DB::statement("UPDATE auth.companies SET currency_id = NULL");
        }

        if ($exchangeRateCount > 0) {
            DB::statement("UPDATE auth.companies SET exchange_rate_id = NULL WHERE exchange_rate_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM public.exchange_rates WHERE id = auth.companies.exchange_rate_id)");
        } else {
            DB::statement("UPDATE auth.companies SET exchange_rate_id = NULL");
        }

        Schema::table('auth.companies', function (Blueprint $table) use ($currencyCount, $exchangeRateCount) {
            if ($currencyCount > 0) {
                $table->foreign('currency_id')
                    ->references('id')->on('public.currencies')
                    ->nullOnDelete();
            }
            if ($exchangeRateCount > 0) {
                $table->foreign('exchange_rate_id')
                    ->references('id')->on('public.exchange_rates')
                    ->nullOnDelete();
            }

            // Add indexes only if they don't exist
            if (!Schema::hasIndex('auth.companies', 'auth_companies_currency_id_index')) {
                $table->index('currency_id');
            }
            if (!Schema::hasIndex('auth.companies', 'auth_companies_exchange_rate_id_index')) {
                $table->index('exchange_rate_id');
            }
        });

        // Create company_user pivot table
        if (!Schema::hasTable('auth.company_user')) {
            Schema::create('auth.company_user', function (Blueprint $table) {
            $table->uuid('company_id');
            $table->uuid('user_id');
            $table->uuid('invited_by_user_id')->nullable();
            $table->string('role')->default('member');
            $table->timestamps();

            $table->primary(['company_id', 'user_id']);

            // Check if foreign keys exist before adding them
            $fkCompanyExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.table_constraints
                WHERE table_schema = 'auth'
                AND table_name = 'company_user'
                AND constraint_name = 'company_user_company_id_foreign'
            ")[0]->count > 0;

            if (!$fkCompanyExists) {
                $table->foreign('company_id')->references('id')->on('auth.companies')->onDelete('cascade');
            }

            $fkUserExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.table_constraints
                WHERE table_schema = 'auth'
                AND table_name = 'company_user'
                AND constraint_name = 'company_user_user_id_foreign'
            ")[0]->count > 0;

            if (!$fkUserExists) {
                $table->foreign('user_id')->references('id')->on('auth.users')->onDelete('cascade');
            }

            $fkInvitedByExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.table_constraints
                WHERE table_schema = 'auth'
                AND table_name = 'company_user'
                AND constraint_name = 'company_user_invited_by_user_id_foreign'
            ")[0]->count > 0;

            if (!$fkInvitedByExists) {
                $table->foreign('invited_by_user_id')->references('id')->on('auth.users')->onDelete('set null');
            }

            $table->index(['company_id', 'user_id']);
            $table->index('invited_by_user_id');
        });
        }

        // Add check constraint for role values (only if table exists and constraint doesn't exist)
        if (Schema::hasTable('auth.company_user')) {
            $constraintExists = DB::select("
                SELECT COUNT(*) as count
                FROM information_schema.check_constraints
                WHERE constraint_name = 'auth_company_user_role_chk'
            ")[0]->count > 0;

            if (!$constraintExists) {
                DB::statement("alter table auth.company_user add constraint auth_company_user_role_chk check (role in ('owner','admin','accountant','viewer','member'))");
            }
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
        }

        // Add foreign key constraints for company_secondary_currencies
        if (Schema::hasTable('auth.company_secondary_currencies')) {
            Schema::table('auth.company_secondary_currencies', function (Blueprint $table) use ($currencyCount, $exchangeRateCount) {
                // Check if foreign key constraints already exist before adding them
                $fkCompanyExists = DB::select("
                    SELECT COUNT(*) as count
                    FROM information_schema.table_constraints
                    WHERE table_schema = 'auth'
                    AND table_name = 'company_secondary_currencies'
                    AND constraint_name = 'auth_company_secondary_currencies_company_id_foreign'
                ")[0]->count > 0;

                if (!$fkCompanyExists) {
                    $table->foreign('company_id')
                        ->references('id')->on('auth.companies')
                        ->onDelete('cascade');
                }

                if ($currencyCount > 0) {
                    $fkCurrencyExists = DB::select("
                        SELECT COUNT(*) as count
                        FROM information_schema.table_constraints
                        WHERE table_schema = 'auth'
                        AND table_name = 'company_secondary_currencies'
                        AND constraint_name = 'auth_company_secondary_currencies_currency_id_foreign'
                    ")[0]->count > 0;

                    if (!$fkCurrencyExists) {
                        $table->foreign('currency_id')
                            ->references('id')->on('public.currencies')
                            ->onDelete('restrict');
                    }
                }

                if ($exchangeRateCount > 0) {
                    $fkExchangeRateExists = DB::select("
                        SELECT COUNT(*) as count
                        FROM information_schema.table_constraints
                        WHERE table_schema = 'auth'
                        AND table_name = 'company_secondary_currencies'
                        AND constraint_name = 'auth_company_secondary_currencies_exchange_rate_id_foreign'
                    ")[0]->count > 0;

                    if (!$fkExchangeRateExists) {
                        $table->foreign('exchange_rate_id')
                            ->references('id')->on('public.exchange_rates')
                            ->onDelete('set null');
                    }
                }
            });
        }

        // Backfill currency_id for existing companies based on base_currency
        if (Schema::hasTable('public.currencies') && Schema::hasTable('auth.companies')) {
            DB::statement('UPDATE auth.companies SET currency_id = public.currencies.id FROM public.currencies WHERE auth.companies.base_currency = public.currencies.code');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            // Drop indexes only if they exist
            Schema::table('auth.companies', function (Blueprint $table) {
                if (Schema::hasIndex('auth.companies', 'auth_companies_currency_id_index')) {
                    $table->dropIndex(['currency_id']);
                }
                if (Schema::hasIndex('auth.companies', 'auth_companies_exchange_rate_id_index')) {
                    $table->dropIndex(['exchange_rate_id']);
                }
            });

            // Drop foreign keys
            Schema::table('auth.companies', function (Blueprint $table) {
                $table->dropForeign(['exchange_rate_id']);
                $table->dropForeign(['currency_id']);
            });

            Schema::dropIfExists('auth.company_secondary_currencies');
            Schema::dropIfExists('auth.company_user');
        } catch (\Throwable $e) {
            // Ignore errors during rollback
        }
    }
};
