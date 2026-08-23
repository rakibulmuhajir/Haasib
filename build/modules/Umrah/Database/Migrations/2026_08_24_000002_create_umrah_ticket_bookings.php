<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A booking is a container: one buyer invoice, one supplier bill, and
 * (from Task 3) the tickets that make up the sale. Both documents are
 * required -- a bill raised later would be converted at a different
 * rate and leave a residual in the supplier-clearing account that
 * nothing closes -- so invoice_id and bill_id are NOT NULL, each
 * unique to its booking, and the whole row is unique per idempotency
 * key so the create-booking command can replay safely.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.ticket_bookings', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('customer_id');                       // the buyer, always
            $table->uuid('agent_id')->nullable();               // set when the buyer is an agent
            $table->uuid('supplier_vendor_id');
            $table->uuid('invoice_id');
            $table->uuid('bill_id');
            $table->string('booking_reference', 32);
            $table->string('pnr', 16)->nullable();
            $table->date('booking_date');
            $table->string('status', 20)->default('confirmed');
            $table->string('idempotency_key', 64);
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('customer_id')->references('id')->on('acct.customers')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('agent_id')->references('id')->on('umrah.agents')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('supplier_vendor_id')->references('id')->on('acct.vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('invoice_id')->references('id')->on('acct.invoices')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('bill_id')->references('id')->on('acct.bills')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();

            $table->unique(['company_id', 'booking_reference']);
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique('invoice_id');
            $table->unique('bill_id');
            $table->index(['company_id', 'customer_id']);
            $table->index(['company_id', 'booking_date']);
            $table->index(['company_id', 'status']);
        });

        DB::statement("ALTER TABLE umrah.ticket_bookings ADD CONSTRAINT umrah_ticket_bookings_status_check CHECK (status IN ('confirmed','cancelled'))");

        DB::statement('ALTER TABLE umrah.ticket_bookings ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.ticket_bookings FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY ticket_bookings_company_isolation ON umrah.ticket_bookings FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS ticket_bookings_company_isolation ON umrah.ticket_bookings');
        Schema::dropIfExists('umrah.ticket_bookings');
    }
};
