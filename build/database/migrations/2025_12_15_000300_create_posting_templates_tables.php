<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS acct');

        Schema::create('acct.posting_templates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('doc_type', 30);
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('version')->default(1);
            $table->date('effective_from')->default(DB::raw('CURRENT_DATE'));
            $table->date('effective_to')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->uuid('updated_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('updated_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'doc_type', 'name'])->whereNull('deleted_at');
            $table->index('company_id');
            $table->index(['company_id', 'doc_type', 'is_active']);
            $table->index(['company_id', 'is_default']);
        });

        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_doc_type_chk
            CHECK (doc_type IN (
                'AR_INVOICE','AR_PAYMENT','AR_CREDIT_NOTE',
                'AP_BILL','AP_PAYMENT','AP_VENDOR_CREDIT',
                'BANK_TRANSFER','BANK_FEE','PAYROLL'
            ))
        ");

        DB::statement("
            ALTER TABLE acct.posting_templates
            ADD CONSTRAINT posting_templates_effective_dates_chk
            CHECK (effective_to IS NULL OR effective_to > effective_from)
        ");

        DB::statement('ALTER TABLE acct.posting_templates ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY posting_templates_policy ON acct.posting_templates
            FOR ALL
            USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
            WITH CHECK (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
            )
        ");

        Schema::create('acct.posting_template_lines', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('template_id');
            $table->string('role', 50);
            $table->uuid('account_id');
            $table->string('description', 255)->nullable();
            $table->smallInteger('precedence')->default(1);
            $table->boolean('is_required')->default(true);
            $table->timestamps();

            $table->foreign('template_id')->references('id')->on('acct.posting_templates')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('account_id')->references('id')->on('acct.accounts')->restrictOnDelete()->cascadeOnUpdate();

            $table->unique(['template_id', 'role']);
            $table->index('template_id');
            $table->index('account_id');
        });

        DB::statement("
            ALTER TABLE acct.posting_template_lines
            ADD CONSTRAINT posting_template_lines_role_chk
            CHECK (role IN (
                'AR','AP','REVENUE','EXPENSE','TAX_PAYABLE','TAX_RECEIVABLE',
                'DISCOUNT_GIVEN','DISCOUNT_RECEIVED','SHIPPING',
                'BANK','CASH','CLEARING','RETAINED_EARNINGS','SUSPENSE'
            ))
        ");

        DB::statement('ALTER TABLE acct.posting_template_lines ENABLE ROW LEVEL SECURITY');
        DB::statement("
            CREATE POLICY posting_template_lines_policy ON acct.posting_template_lines
            FOR ALL
            USING (
                EXISTS (
                    SELECT 1
                    FROM acct.posting_templates t
                    WHERE t.id = acct.posting_template_lines.template_id
                      AND (
                        t.company_id = current_setting('app.current_company_id', true)::uuid
                        OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
                      )
                )
            )
            WITH CHECK (
                EXISTS (
                    SELECT 1
                    FROM acct.posting_templates t
                    WHERE t.id = acct.posting_template_lines.template_id
                      AND (
                        t.company_id = current_setting('app.current_company_id', true)::uuid
                        OR coalesce(current_setting('app.is_super_admin', true)::boolean, false)
                      )
                )
            )
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS posting_template_lines_policy ON acct.posting_template_lines');
        DB::statement('DROP POLICY IF EXISTS posting_templates_policy ON acct.posting_templates');
        Schema::dropIfExists('acct.posting_template_lines');
        Schema::dropIfExists('acct.posting_templates');
    }
};

