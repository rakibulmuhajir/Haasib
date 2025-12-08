<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax.company_tax_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('jurisdiction_id');
            $table->string('registration_number', 100);
            $table->string('registration_type', 50)->default('vat');
            $table->string('registered_name', 255)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('jurisdiction_id')->references('id')->on('tax.jurisdictions')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'jurisdiction_id', 'registration_number']);
            $table->index('company_id');
            $table->index(['company_id', 'is_active']);
        });

        DB::statement("ALTER TABLE tax.company_tax_registrations ENABLE ROW LEVEL SECURITY");
        DB::statement("
            CREATE POLICY company_tax_registrations_policy ON tax.company_tax_registrations
            FOR ALL USING (
                company_id = current_setting('app.current_company_id', true)::uuid
                OR current_setting('app.is_super_admin', true)::boolean = true
            )
        ");

        // Add check constraints
        DB::statement("
            ALTER TABLE tax.company_tax_registrations
            ADD CONSTRAINT company_tax_registrations_registration_type_chk
            CHECK (registration_type IN ('vat', 'gst', 'sales_tax', 'withholding', 'other'))
        ");

        DB::statement("
            ALTER TABLE tax.company_tax_registrations
            ADD CONSTRAINT company_tax_registrations_effective_dates_chk
            CHECK (effective_to IS NULL OR effective_to > effective_from)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS company_tax_registrations_policy ON tax.company_tax_registrations');
        Schema::dropIfExists('tax.company_tax_registrations');
    }
};