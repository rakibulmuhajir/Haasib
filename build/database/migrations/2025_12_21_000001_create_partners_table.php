<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Create partners table in auth schema.
 * Partners are business co-owners with profit sharing and drawing limits.
 * This is a universal feature for any company, regardless of industry.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // auth.partners - Business partners/owners with profit sharing
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('auth.partners', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('user_id')->nullable(); // Optional link to system user
            $table->string('name', 255);
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('cnic', 20)->nullable(); // Pakistani national ID
            $table->text('address')->nullable();

            // Profit sharing
            $table->decimal('profit_share_percentage', 5, 2)->default(0); // e.g., 33.33%

            // Drawing limits
            $table->string('drawing_limit_period', 20)->default('monthly'); // monthly, yearly, none
            $table->decimal('drawing_limit_amount', 15, 2)->nullable(); // Max withdrawal per period

            // GL account for partner drawings (sub-ledger)
            $table->uuid('drawing_account_id')->nullable();

            // Tracking
            $table->decimal('total_invested', 15, 2)->default(0); // Capital contributions
            $table->decimal('total_withdrawn', 15, 2)->default(0); // Total drawings
            $table->decimal('current_period_withdrawn', 15, 2)->default(0); // Resets each period
            $table->date('period_reset_date')->nullable(); // Last period reset

            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('drawing_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        // Check constraint for drawing_limit_period enum
        DB::statement("ALTER TABLE auth.partners ADD CONSTRAINT partners_limit_period_check
            CHECK (drawing_limit_period IN ('monthly', 'yearly', 'none'))");

        // Check constraint for profit share (0-100%)
        DB::statement("ALTER TABLE auth.partners ADD CONSTRAINT partners_profit_share_check
            CHECK (profit_share_percentage >= 0 AND profit_share_percentage <= 100)");

        // ─────────────────────────────────────────────────────────────────────
        // auth.partner_transactions - Partner capital movements (investments + withdrawals)
        // ─────────────────────────────────────────────────────────────────────
        Schema::create('auth.partner_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('partner_id');
            $table->date('transaction_date');
            $table->string('transaction_type', 20); // investment, withdrawal, adjustment

            $table->decimal('amount', 15, 2);
            $table->string('description', 255)->nullable();
            $table->string('reference', 100)->nullable(); // External reference

            // GL integration
            $table->uuid('journal_entry_id')->nullable(); // Links to acct.journal_entries

            // For withdrawals - payment method
            $table->string('payment_method', 30)->nullable(); // cash, bank_transfer, cheque
            $table->uuid('bank_account_id')->nullable(); // Which bank account used

            $table->uuid('recorded_by_user_id')->nullable();
            $table->timestamps();

            // Foreign keys
            $table->foreign('company_id')
                ->references('id')->on('auth.companies')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('partner_id')
                ->references('id')->on('auth.partners')
                ->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('journal_entry_id')
                ->references('id')->on('acct.journal_entries')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('bank_account_id')
                ->references('id')->on('acct.accounts')
                ->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('recorded_by_user_id')
                ->references('id')->on('auth.users')
                ->nullOnDelete()->cascadeOnUpdate();

            // Indexes
            $table->index('company_id');
            $table->index('partner_id');
            $table->index(['company_id', 'transaction_date']);
            $table->index(['partner_id', 'transaction_type']);
        });

        // Check constraint for transaction_type enum
        DB::statement("ALTER TABLE auth.partner_transactions ADD CONSTRAINT partner_transactions_type_check
            CHECK (transaction_type IN ('investment', 'withdrawal', 'adjustment'))");

        // ─────────────────────────────────────────────────────────────────────
        // RLS Policies
        // ─────────────────────────────────────────────────────────────────────
        $tables = ['partners', 'partner_transactions'];

        foreach ($tables as $tableName) {
            DB::statement("ALTER TABLE auth.{$tableName} ENABLE ROW LEVEL SECURITY");

            // Company isolation policy with super-admin override
            DB::statement("
                CREATE POLICY {$tableName}_company_isolation ON auth.{$tableName}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR current_setting('app.is_super_admin', true)::boolean = true
                )
            ");
        }

        // ─────────────────────────────────────────────────────────────────────
        // Trigger: Update partner totals when transactions change
        // ─────────────────────────────────────────────────────────────────────
        DB::statement("
            CREATE OR REPLACE FUNCTION auth.update_partner_totals()
            RETURNS TRIGGER AS \$\$
            BEGIN
                UPDATE auth.partners
                SET
                    total_invested = COALESCE((
                        SELECT SUM(amount) FROM auth.partner_transactions
                        WHERE partner_id = COALESCE(NEW.partner_id, OLD.partner_id)
                        AND transaction_type = 'investment'
                    ), 0),
                    total_withdrawn = COALESCE((
                        SELECT SUM(amount) FROM auth.partner_transactions
                        WHERE partner_id = COALESCE(NEW.partner_id, OLD.partner_id)
                        AND transaction_type = 'withdrawal'
                    ), 0),
                    updated_at = NOW()
                WHERE id = COALESCE(NEW.partner_id, OLD.partner_id);

                RETURN COALESCE(NEW, OLD);
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_partner_transactions_update_totals
            AFTER INSERT OR UPDATE OR DELETE ON auth.partner_transactions
            FOR EACH ROW
            EXECUTE FUNCTION auth.update_partner_totals();
        ");

        // ─────────────────────────────────────────────────────────────────────
        // Add partner-related columns to company settings
        // ─────────────────────────────────────────────────────────────────────
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->string('default_drawing_limit_period', 20)->nullable()->after('settings');
            $table->decimal('default_drawing_limit_amount', 15, 2)->nullable()->after('default_drawing_limit_period');
        });
    }

    public function down(): void
    {
        // Drop trigger and function
        DB::statement('DROP TRIGGER IF EXISTS trg_partner_transactions_update_totals ON auth.partner_transactions');
        DB::statement('DROP FUNCTION IF EXISTS auth.update_partner_totals()');

        // Drop tables in reverse order
        Schema::dropIfExists('auth.partner_transactions');
        Schema::dropIfExists('auth.partners');

        // Remove company columns
        Schema::table('auth.companies', function (Blueprint $table) {
            $table->dropColumn(['default_drawing_limit_period', 'default_drawing_limit_amount']);
        });
    }
};
