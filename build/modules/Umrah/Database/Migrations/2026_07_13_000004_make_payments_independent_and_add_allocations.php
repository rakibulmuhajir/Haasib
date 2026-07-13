<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.payment_allocations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('group_payment_id');
            $table->uuid('visa_group_id');
            $table->decimal('base_amount', 15, 2);
            $table->uuid('transaction_id')->nullable();
            $table->timestamps();
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('group_payment_id')->references('id')->on('umrah.group_payments')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['group_payment_id', 'visa_group_id']);
            $table->index(['company_id', 'visa_group_id']);
        });
        DB::statement('ALTER TABLE umrah.payment_allocations ADD CONSTRAINT payment_allocations_amount_check CHECK (base_amount > 0)');

        DB::statement('ALTER TABLE umrah.group_payments DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_payee_check');
        DB::statement('INSERT INTO umrah.payment_allocations (company_id, group_payment_id, visa_group_id, base_amount, created_at, updated_at) SELECT company_id, id, visa_group_id, base_amount, created_at, updated_at FROM umrah.group_payments WHERE visa_group_id IS NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN visa_group_id DROP NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN agent_id DROP NOT NULL');
        DB::statement("UPDATE umrah.group_payments SET agent_id = NULL WHERE direction = 'sent'");
        DB::statement('UPDATE umrah.group_payments SET visa_group_id = NULL');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_payee_check CHECK ((direction = 'received' AND agent_id IS NOT NULL AND visa_vendor_id IS NULL AND hotel_vendor_id IS NULL) OR (direction = 'sent' AND agent_id IS NULL AND ((visa_vendor_id IS NOT NULL AND hotel_vendor_id IS NULL) OR (visa_vendor_id IS NULL AND hotel_vendor_id IS NOT NULL))))");
        DB::statement('ALTER TABLE umrah.group_payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.group_payments FORCE ROW LEVEL SECURITY');

        DB::statement('ALTER TABLE umrah.payment_allocations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.payment_allocations FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY payment_allocations_company_isolation ON umrah.payment_allocations FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE umrah.group_payments DISABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_payee_check');
        DB::statement('UPDATE umrah.group_payments AS payment SET visa_group_id = allocation.visa_group_id FROM umrah.payment_allocations AS allocation WHERE allocation.group_payment_id = payment.id');
        DB::statement('UPDATE umrah.group_payments AS payment SET agent_id = group_record.agent_id FROM umrah.visa_groups AS group_record WHERE payment.visa_group_id = group_record.id AND payment.agent_id IS NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN visa_group_id SET NOT NULL');
        DB::statement('ALTER TABLE umrah.group_payments ALTER COLUMN agent_id SET NOT NULL');
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_payee_check CHECK ((direction = 'received' AND visa_vendor_id IS NULL AND hotel_vendor_id IS NULL) OR (direction = 'sent' AND ((visa_vendor_id IS NOT NULL AND hotel_vendor_id IS NULL) OR (visa_vendor_id IS NULL AND hotel_vendor_id IS NOT NULL))))");
        DB::statement('ALTER TABLE umrah.group_payments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.group_payments FORCE ROW LEVEL SECURITY');
        Schema::dropIfExists('umrah.payment_allocations');
    }
};
