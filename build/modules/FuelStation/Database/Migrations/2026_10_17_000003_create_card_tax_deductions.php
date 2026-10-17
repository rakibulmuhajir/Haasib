<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tax a card company deducted from a station's card sales (bank POS swipes, or a fuel supplier's
 * vendor card), recorded by hand on any date -- usually month end, when the statement shows it.
 * It becomes advance income tax, taken from wherever that channel's money sits: its clearing
 * account, its bank, or (for a supplier-settled card) a bill to that supplier. Undo soft-deletes
 * the row and reverses its posting.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fuel.card_tax_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('channel_code', 50);
            $table->string('channel_label', 120);
            $table->string('settles_to', 20);
            $table->date('deduction_date');
            $table->decimal('amount', 15, 2);
            $table->string('section', 30)->nullable();
            $table->string('reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->uuid('transaction_id')->nullable();
            $table->uuid('bill_id')->nullable();
            $table->uuid('vendor_id')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->index(['company_id', 'deduction_date']);
        });

        DB::statement('ALTER TABLE fuel.card_tax_deductions ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE fuel.card_tax_deductions FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY card_tax_deductions_company_isolation ON fuel.card_tax_deductions
            FOR ALL
            USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)
            WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS card_tax_deductions_company_isolation ON fuel.card_tax_deductions');
        Schema::dropIfExists('fuel.card_tax_deductions');
    }
};
