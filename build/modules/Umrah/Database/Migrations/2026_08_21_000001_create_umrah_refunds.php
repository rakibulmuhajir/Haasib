<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of docs/contracts/refunds.md -- "The obligation".
 *
 * A refund is recorded and lives through request/approve/reject/cancel here.
 * No ledger effect is wired up yet: `transaction_id` and `settled_payment_id`
 * are seams for Phase 2 (settlement) and Phase 3 (surfacing), left nullable
 * and unused by anything in this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.refunds', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('party_type', 20);
            $table->uuid('party_id');
            $table->uuid('visa_group_id')->nullable();
            $table->string('service', 20);
            $table->string('refund_number', 50);
            $table->decimal('amount', 18, 2);
            $table->char('currency', 3);
            $table->decimal('exchange_rate', 18, 8)->nullable();
            $table->char('base_currency', 3);
            $table->decimal('base_amount', 15, 2);
            $table->text('reason');
            $table->string('status', 20)->default('requested');
            $table->uuid('requested_by_user_id')->nullable();
            $table->timestamp('requested_at')->nullable();
            $table->uuid('reviewed_by_user_id')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_remarks')->nullable();
            $table->uuid('settled_payment_id')->nullable();
            $table->uuid('transaction_id')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->uuid('cancelled_by_user_id')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('settled_payment_id')->references('id')->on('umrah.group_payments')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('requested_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('reviewed_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('cancelled_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            // party_id is polymorphic across umrah.agents / umrah.visa_vendors /
            // umrah.hotel_vendors depending on party_type, so it cannot carry a
            // single foreign key -- validated at the application layer, the same
            // shape GroupPayment already uses for its three vendor columns.

            $table->unique(['company_id', 'refund_number']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'party_type', 'party_id']);
        });

        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_party_type_check CHECK (party_type IN ('agent', 'visa_vendor', 'transport_vendor', 'hotel_vendor'))");
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_service_check CHECK (service IN ('visa', 'transport', 'hotel', 'other'))");
        DB::statement("ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_status_check CHECK (status IN ('requested', 'approved', 'paid', 'rejected', 'cancelled'))");
        // Invariant 1: amount is always positive, direction is carried by party_type.
        DB::statement('ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_amount_check CHECK (amount > 0 AND base_amount > 0)');
        DB::statement('ALTER TABLE umrah.refunds ADD CONSTRAINT umrah_refunds_exchange_rate_check CHECK ((currency = base_currency AND exchange_rate IS NULL AND base_amount = round(amount, 2)) OR (currency <> base_currency AND exchange_rate > 0 AND base_amount = round(amount * exchange_rate, 2)))');

        DB::statement('ALTER TABLE umrah.refunds ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.refunds FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY refunds_company_isolation ON umrah.refunds FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS refunds_company_isolation ON umrah.refunds');
        Schema::dropIfExists('umrah.refunds');
    }
};
