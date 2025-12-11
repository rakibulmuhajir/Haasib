<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Accounts Receivable Tables Migration
 *
 * Creates: customers, recurring_schedules, invoices, invoice_line_items,
 *          credit_notes, credit_note_items, credit_note_applications,
 *          payments, payment_allocations
 *
 * Dependency order:
 * 1. customers (base AR entity)
 * 2. recurring_schedules (references customers)
 * 3. invoices (references customers, recurring_schedules)
 * 4. invoice_line_items (references invoices)
 * 5. credit_notes (references customers, invoices)
 * 6. credit_note_items (references credit_notes)
 * 7. credit_note_applications (references credit_notes, invoices)
 * 8. payments (references customers)
 * 9. payment_allocations (references payments, invoices)
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Customers
        Schema::create('acct.customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('customer_number', 50);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('billing_address')->nullable();
            $table->jsonb('shipping_address')->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->char('base_currency', 3)->default('USD');
            $table->integer('payment_terms')->default(30);
            $table->decimal('credit_limit', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
        });

        DB::statement('CREATE UNIQUE INDEX customers_company_number_unique ON acct.customers (company_id, customer_number) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX customers_company_email_unique ON acct.customers (company_id, email) WHERE email IS NOT NULL AND deleted_at IS NULL');
        DB::statement('CREATE INDEX customers_company_active_index ON acct.customers (company_id, is_active) WHERE deleted_at IS NULL');

        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_payment_terms_range CHECK (payment_terms >= 0 AND payment_terms <= 365)");
        DB::statement("ALTER TABLE acct.customers ADD CONSTRAINT customers_credit_limit_non_negative CHECK (credit_limit IS NULL OR credit_limit >= 0)");

        DB::statement('ALTER TABLE acct.customers ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY customers_company_policy ON acct.customers
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // 2. Recurring Schedules
        Schema::create('acct.recurring_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('name', 255);
            $table->string('frequency', 20);
            $table->integer('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_invoice_date');
            $table->timestamp('last_generated_at')->nullable();
            $table->jsonb('template_data');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();

            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['company_id', 'is_active', 'next_invoice_date']);
            $table->index(['company_id', 'is_active']);
            $table->index(['next_invoice_date']);
            $table->index(['start_date']);
            $table->index(['end_date']);
        });

        DB::statement('ALTER TABLE acct.recurring_schedules ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY recurring_schedules_company_policy ON acct.recurring_schedules
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.recurring_schedules ADD CONSTRAINT recurring_schedules_interval_positive CHECK (interval > 0)");
        DB::statement("ALTER TABLE acct.recurring_schedules ADD CONSTRAINT recurring_schedules_valid_frequency CHECK (frequency IN ('daily', 'weekly', 'monthly', 'quarterly', 'yearly'))");
        DB::statement("ALTER TABLE acct.recurring_schedules ADD CONSTRAINT recurring_schedules_date_logic CHECK (end_date IS NULL OR end_date >= start_date)");
        DB::statement("ALTER TABLE acct.recurring_schedules ADD CONSTRAINT recurring_schedules_next_date_logic CHECK (next_invoice_date >= start_date AND (end_date IS NULL OR next_invoice_date <= end_date))");

        // 3. Invoices
        Schema::create('acct.invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('invoice_number', 50);
            $table->date('invoice_date')->default(DB::raw('current_date'));
            $table->date('due_date');
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default('USD');
            $table->char('base_currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('subtotal', 18, 6)->default(0.00);
            $table->decimal('tax_amount', 18, 6)->default(0.00);
            $table->decimal('discount_amount', 18, 6)->default(0.00);
            $table->decimal('total_amount', 18, 6)->default(0.00);
            $table->decimal('paid_amount', 18, 6)->default(0.00);
            $table->decimal('balance', 18, 6)->default(0.00);
            $table->decimal('base_amount', 15, 2)->default(0.00);
            $table->integer('payment_terms')->default(30);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('recurring_schedule_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('recurring_schedule_id')->references('id')->on('acct.recurring_schedules')->nullOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'invoice_date', 'due_date']);
            $table->index(['company_id', 'due_date', 'status']);
        });

        DB::statement('CREATE UNIQUE INDEX invoices_company_number_unique ON acct.invoices (company_id, invoice_number) WHERE deleted_at IS NULL');

        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_currency_format CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_status_enum CHECK (status IN ('draft','sent','viewed','partial','paid','overdue','void','cancelled'))");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_payment_terms_range CHECK (payment_terms >= 0 AND payment_terms <= 365)");
        DB::statement("ALTER TABLE acct.invoices ADD CONSTRAINT invoices_amounts_non_negative CHECK (subtotal >= 0 AND tax_amount >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND paid_amount >= 0 AND balance >= 0 AND base_amount >= 0)");

        DB::statement('ALTER TABLE acct.invoices ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY invoices_company_policy ON acct.invoices
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // 4. Invoice Line Items
        Schema::create('acct.invoice_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('invoice_id');
            $table->integer('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('discount_rate', 5, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index(['company_id']);
            $table->index(['invoice_id', 'line_number']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'invoice_id']);

            $table->unique(['invoice_id', 'line_number'], 'unique_invoice_line_number');
        });

        DB::statement('ALTER TABLE acct.invoice_line_items ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY invoice_line_items_company_policy ON acct.invoice_line_items
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.invoice_line_items ADD CONSTRAINT invoice_line_items_positive_values CHECK (quantity > 0 AND unit_price >= 0 AND tax_rate >= 0 AND discount_rate >= 0 AND line_total >= 0 AND tax_amount >= 0 AND total >= 0 AND line_number > 0)");
        DB::statement("ALTER TABLE acct.invoice_line_items ADD CONSTRAINT invoice_line_items_discount_max CHECK (discount_rate <= 100)");

        // 5. Credit Notes
        Schema::create('acct.credit_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->uuid('invoice_id')->nullable();
            $table->string('credit_note_number', 50);
            $table->date('credit_date')->default(DB::raw('current_date'));
            $table->decimal('amount', 15, 2)->default(0.00);
            $table->char('base_currency', 3)->default('USD');
            $table->string('reason', 255);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('cancellation_reason')->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index(['company_id']);
            $table->index(['customer_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'credit_note_number']);
            $table->index(['status']);
            $table->index(['credit_date']);

            $table->unique(['company_id', 'credit_note_number'], 'unique_credit_note_per_company');
        });

        DB::statement('ALTER TABLE acct.credit_notes ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY credit_notes_company_policy ON acct.credit_notes
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.credit_notes ADD CONSTRAINT credit_notes_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE acct.credit_notes ADD CONSTRAINT credit_notes_valid_status CHECK (status IN ('draft', 'issued', 'applied', 'void'))");
        DB::statement("ALTER TABLE acct.credit_notes ADD CONSTRAINT credit_notes_valid_currency CHECK (length(trim(base_currency)) = 3 AND base_currency ~ '^[A-Z]{3}$')");

        // 6. Credit Note Items
        Schema::create('acct.credit_note_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('credit_note_id');
            $table->integer('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 15, 2)->default(0.00);
            $table->decimal('tax_rate', 5, 2)->default(0.00);
            $table->decimal('discount_rate', 5, 2)->default(0.00);
            $table->decimal('line_total', 15, 2)->default(0.00);
            $table->decimal('tax_amount', 15, 2)->default(0.00);
            $table->decimal('total', 15, 2)->default(0.00);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('credit_note_id')->references('id')->on('acct.credit_notes')->cascadeOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index(['company_id']);
            $table->index(['credit_note_id', 'line_number']);
            $table->index(['credit_note_id']);
            $table->index(['company_id', 'credit_note_id']);

            $table->unique(['credit_note_id', 'line_number'], 'unique_credit_note_line_number');
        });

        DB::statement('ALTER TABLE acct.credit_note_items ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY credit_note_items_company_policy ON acct.credit_note_items
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.credit_note_items ADD CONSTRAINT credit_note_items_positive_values CHECK (quantity > 0 AND unit_price >= 0 AND tax_rate >= 0 AND discount_rate >= 0 AND line_total >= 0 AND tax_amount >= 0 AND total >= 0 AND line_number > 0)");
        DB::statement("ALTER TABLE acct.credit_note_items ADD CONSTRAINT credit_note_items_discount_max CHECK (discount_rate <= 100)");

        // 7. Credit Note Applications
        Schema::create('acct.credit_note_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('credit_note_id');
            $table->uuid('invoice_id');
            $table->decimal('amount_applied', 15, 2);
            $table->timestamp('applied_at')->default(DB::raw('now()'));
            $table->uuid('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('invoice_balance_before', 15, 2);
            $table->decimal('invoice_balance_after', 15, 2);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('credit_note_id')->references('id')->on('acct.credit_notes')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index(['company_id']);
            $table->index(['credit_note_id']);
            $table->index(['invoice_id']);
            $table->index(['company_id', 'credit_note_id']);
            $table->index(['company_id', 'invoice_id']);
            $table->index(['applied_at']);

            $table->unique(['credit_note_id', 'invoice_id'], 'unique_credit_note_invoice_application');
        });

        DB::statement('ALTER TABLE acct.credit_note_applications ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY credit_note_applications_company_policy ON acct.credit_note_applications
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        DB::statement("ALTER TABLE acct.credit_note_applications ADD CONSTRAINT credit_note_applications_amount_positive CHECK (amount_applied > 0)");
        DB::statement("ALTER TABLE acct.credit_note_applications ADD CONSTRAINT credit_note_applications_balance_consistency CHECK (invoice_balance_before >= invoice_balance_after)");

        // 8. Payments
        Schema::create('acct.payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('customer_id');
            $table->string('payment_number', 50);
            $table->date('payment_date')->default(DB::raw('current_date'));
            $table->decimal('amount', 18, 6)->default(0.00);
            $table->char('currency', 3)->default('USD');
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3);
            $table->decimal('base_amount', 15, 2)->default(0.00);
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete();

            $table->index('company_id');
            $table->index('customer_id');
            $table->index('payment_date');
        });

        DB::statement('CREATE UNIQUE INDEX payments_company_number_unique ON acct.payments (company_id, payment_number) WHERE deleted_at IS NULL');

        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_currency_format CHECK (currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_base_currency_format CHECK (base_currency ~ '^[A-Z]{3}$')");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_base_amount_non_negative CHECK (base_amount >= 0)");
        DB::statement("ALTER TABLE acct.payments ADD CONSTRAINT payments_payment_method_enum CHECK (payment_method IN ('cash','check','card','bank_transfer','other'))");

        DB::statement('ALTER TABLE acct.payments ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY payments_company_policy ON acct.payments
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // 9. Payment Allocations
        Schema::create('acct.payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('payment_id');
            $table->uuid('invoice_id');
            $table->decimal('amount_allocated', 18, 6);
            $table->decimal('base_amount_allocated', 15, 2)->default(0.00);
            $table->timestamp('applied_at')->default(DB::raw('now()'));
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete();
            $table->foreign('payment_id')->references('id')->on('acct.payments')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete();

            $table->index('company_id');
            $table->index('payment_id');
            $table->index('invoice_id');
        });

        DB::statement("ALTER TABLE acct.payment_allocations ADD CONSTRAINT payment_allocations_amount_positive CHECK (amount_allocated > 0)");
        DB::statement("ALTER TABLE acct.payment_allocations ADD CONSTRAINT payment_allocations_base_amount_non_negative CHECK (base_amount_allocated >= 0)");

        DB::statement('ALTER TABLE acct.payment_allocations ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY payment_allocations_company_policy ON acct.payment_allocations
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");
    }

    public function down(): void
    {
        // Drop in reverse order
        DB::statement('DROP POLICY IF EXISTS payment_allocations_company_policy ON acct.payment_allocations');
        Schema::dropIfExists('acct.payment_allocations');

        DB::statement('DROP POLICY IF EXISTS payments_company_policy ON acct.payments');
        Schema::dropIfExists('acct.payments');

        DB::statement('DROP POLICY IF EXISTS credit_note_applications_company_policy ON acct.credit_note_applications');
        Schema::dropIfExists('acct.credit_note_applications');

        DB::statement('DROP POLICY IF EXISTS credit_note_items_company_policy ON acct.credit_note_items');
        Schema::dropIfExists('acct.credit_note_items');

        DB::statement('DROP POLICY IF EXISTS credit_notes_company_policy ON acct.credit_notes');
        Schema::dropIfExists('acct.credit_notes');

        DB::statement('DROP POLICY IF EXISTS invoice_line_items_company_policy ON acct.invoice_line_items');
        Schema::dropIfExists('acct.invoice_line_items');

        DB::statement('DROP POLICY IF EXISTS invoices_company_policy ON acct.invoices');
        Schema::dropIfExists('acct.invoices');

        DB::statement('DROP POLICY IF EXISTS recurring_schedules_company_policy ON acct.recurring_schedules');
        Schema::dropIfExists('acct.recurring_schedules');

        DB::statement('DROP POLICY IF EXISTS customers_company_policy ON acct.customers');
        Schema::dropIfExists('acct.customers');
    }
};
