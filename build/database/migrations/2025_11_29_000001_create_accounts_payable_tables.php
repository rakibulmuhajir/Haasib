<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acct.vendors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->string('vendor_number', 50);
            $table->string('name', 255);
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->jsonb('address')->nullable();
            $table->string('tax_id', 100)->nullable();
            $table->char('base_currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->integer('payment_terms')->default(30);
            $table->string('account_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->string('website', 500)->nullable();
            $table->string('logo_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')
                ->references('id')
                ->on('auth.companies')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies');

            $table->unique(['company_id', 'vendor_number'])->whereNull('deleted_at');
            $table->unique(['company_id', 'email'])->whereNull('deleted_at')->whereNotNull('email');
            $table->index('company_id');
        });

        Schema::create('acct.recurring_bill_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->string('name', 255);
            $table->string('frequency', 20);
            $table->integer('interval')->default(1);
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('next_bill_date');
            $table->timestamp('last_generated_at')->nullable();
            $table->jsonb('template_data');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('vendor_id');
            $table->index(['company_id', 'is_active', 'next_bill_date']);
        });

        Schema::create('acct.bills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->string('bill_number', 50);
            $table->string('vendor_invoice_number', 100)->nullable();
            $table->date('bill_date')->default(DB::raw('current_date'));
            $table->date('due_date');
            $table->string('status', 20)->default('draft');
            $table->char('currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->char('base_currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('subtotal', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('discount_amount', 18, 6)->default(0);
            $table->decimal('total_amount', 18, 6)->default(0);
            $table->decimal('paid_amount', 18, 6)->default(0);
            $table->decimal('balance', 18, 6)->default(0);
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->integer('payment_terms')->default(30);
            $table->text('notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->uuid('recurring_schedule_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('recurring_schedule_id')->references('id')->on('acct.recurring_bill_schedules')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('base_currency')->references('code')->on('public.currencies');

            $table->unique(['company_id', 'bill_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index('vendor_id');
            $table->index(['company_id', 'status'])->whereNull('deleted_at');
            $table->index(['company_id', 'bill_date', 'due_date']);
        });

        Schema::create('acct.bill_line_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('bill_id');
            $table->integer('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->uuid('account_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')->references('id')->on('acct.bills')->cascadeOnDelete()->cascadeOnUpdate();
            if (Schema::hasTable('acct.accounts')) {
                $table->foreign('account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index(['bill_id', 'line_number']);
        });

        Schema::create('acct.bill_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->string('payment_number', 50);
            $table->date('payment_date')->default(DB::raw('current_date'));
            $table->decimal('amount', 18, 6)->default(0);
            $table->char('currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->string('payment_method', 50);
            $table->string('reference_number', 100)->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('base_currency')->references('code')->on('public.currencies');

            $table->unique(['company_id', 'payment_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index('vendor_id');
            $table->index('payment_date');
        });

        Schema::create('acct.bill_payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('bill_payment_id');
            $table->uuid('bill_id');
            $table->decimal('amount_allocated', 18, 6);
            $table->decimal('base_amount_allocated', 15, 2)->default(0);
            $table->timestamp('applied_at')->useCurrent();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_payment_id')->references('id')->on('acct.bill_payments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')->references('id')->on('acct.bills')->restrictOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('bill_payment_id');
            $table->index('bill_id');
        });

        Schema::create('acct.vendor_credits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_id');
            $table->uuid('bill_id')->nullable();
            $table->string('credit_number', 50);
            $table->string('vendor_credit_number', 100)->nullable();
            $table->date('credit_date')->default(DB::raw('current_date'));
            $table->decimal('amount', 18, 6)->default(0);
            $table->char('currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->char('base_currency', 3)->default(DB::raw("current_setting('app.company_base_currency', true)"));
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->decimal('base_amount', 15, 2)->default(0);
            $table->string('reason', 255);
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->string('cancellation_reason', 255)->nullable();
            $table->uuid('journal_entry_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')->references('id')->on('acct.bills')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies');
            $table->foreign('base_currency')->references('code')->on('public.currencies');

            $table->unique(['company_id', 'credit_number'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index('vendor_id');
            $table->index('bill_id');
            $table->index(['company_id', 'status']);
        });

        Schema::create('acct.vendor_credit_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_credit_id');
            $table->integer('line_number');
            $table->string('description', 500);
            $table->decimal('quantity', 10, 2)->default(1.00);
            $table->decimal('unit_price', 18, 6)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('discount_rate', 5, 2)->default(0);
            $table->decimal('line_total', 18, 6)->default(0);
            $table->decimal('tax_amount', 18, 6)->default(0);
            $table->decimal('total', 18, 6)->default(0);
            $table->uuid('account_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_credit_id')->references('id')->on('acct.vendor_credits')->cascadeOnDelete()->cascadeOnUpdate();
            if (Schema::hasTable('acct.accounts')) {
                $table->foreign('account_id')->references('id')->on('acct.accounts')->nullOnDelete()->cascadeOnUpdate();
            }
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->unique(['vendor_credit_id', 'line_number']);
        });

        Schema::create('acct.vendor_credit_applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('company_id');
            $table->uuid('vendor_credit_id');
            $table->uuid('bill_id');
            $table->decimal('amount_applied', 18, 6);
            $table->timestamp('applied_at')->useCurrent();
            $table->uuid('user_id')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('bill_balance_before', 15, 2);
            $table->decimal('bill_balance_after', 15, 2);
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('vendor_credit_id')->references('id')->on('acct.vendor_credits')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')->references('id')->on('acct.bills')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->index('company_id');
            $table->index('vendor_credit_id');
            $table->index('bill_id');
            $table->index('applied_at');
            $table->unique(['vendor_credit_id', 'bill_id']);
        });

        DB::statement("CREATE INDEX vendors_company_is_active_idx ON acct.vendors (company_id, is_active) WHERE deleted_at IS NULL");
        DB::statement("CREATE INDEX bills_company_due_date_status_idx ON acct.bills (company_id, due_date, status) WHERE status NOT IN ('paid','void','cancelled')");

        $this->enableRls('acct.vendors', 'vendors_policy');
        $this->enableRls('acct.bills', 'bills_policy');
        $this->enableRls('acct.bill_line_items', 'bill_line_items_policy');
        $this->enableRls('acct.bill_payments', 'bill_payments_policy');
        $this->enableRls('acct.bill_payment_allocations', 'bill_payment_allocations_policy');
        $this->enableRls('acct.vendor_credits', 'vendor_credits_policy');
        $this->enableRls('acct.vendor_credit_items', 'vendor_credit_items_policy');
        $this->enableRls('acct.vendor_credit_applications', 'vendor_credit_applications_policy');
        $this->enableRls('acct.recurring_bill_schedules', 'recurring_bill_schedules_policy');
    }

    public function down(): void
    {
        Schema::dropIfExists('acct.vendor_credit_applications');
        Schema::dropIfExists('acct.vendor_credit_items');
        Schema::dropIfExists('acct.vendor_credits');
        Schema::dropIfExists('acct.bill_payment_allocations');
        Schema::dropIfExists('acct.bill_payments');
        Schema::dropIfExists('acct.bill_line_items');
        Schema::dropIfExists('acct.bills');
        Schema::dropIfExists('acct.recurring_bill_schedules');
        Schema::dropIfExists('acct.vendors');
    }

    private function enableRls(string $table, string $policyName): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY {$policyName} ON {$table}
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");
    }
};
