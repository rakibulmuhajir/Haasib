<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A cancellation is the second half of a sale: it undoes at most one
 * ticket, records what each side actually gave back (which need not
 * match what was originally charged), and derives its cost the same
 * way the ledger does -- buyer_returns_base minus supplier_returns_base
 * -- so the model and the journal never disagree.
 *
 * Cash settlement (acct.customer_refunds, acct.vendor_refund_receipts)
 * is a deferred follow-on -- buyer_refund_id and supplier_refund_id
 * are plain uuid columns with no foreign key yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.ticket_cancellations', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('ticket_id');
            $table->date('cancellation_date');

            // Supplier side: what the supplier actually returned.
            $table->char('supplier_returns_currency', 3);
            $table->decimal('supplier_returns_exchange_rate', 18, 8)->nullable();
            $table->decimal('supplier_returns_amount', 18, 6);
            $table->decimal('supplier_returns_base', 15, 2);

            // Buyer side: what the buyer actually got back.
            $table->char('buyer_returns_currency', 3);
            $table->decimal('buyer_returns_exchange_rate', 18, 8)->nullable();
            $table->decimal('buyer_returns_amount', 18, 6);
            $table->decimal('buyer_returns_base', 15, 2);

            $table->char('base_currency', 3);

            $table->uuid('buyer_credit_note_id')->nullable();
            $table->uuid('supplier_vendor_credit_id')->nullable();

            // Deferred follow-on (see class docblock) -- no FK yet.
            $table->uuid('buyer_refund_id')->nullable();
            $table->uuid('supplier_refund_receipt_id')->nullable();

            $table->string('idempotency_key', 64);
            $table->text('reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('ticket_id')->references('id')->on('umrah.tickets')->restrictOnDelete()->cascadeOnUpdate();
            $table->foreign('buyer_credit_note_id')->references('id')->on('acct.credit_notes')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('supplier_vendor_credit_id')->references('id')->on('acct.vendor_credits')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('supplier_returns_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('buyer_returns_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();

            $table->unique('ticket_id');                       // a ticket cancels once
            $table->unique(['company_id', 'idempotency_key']);
            $table->unique('buyer_credit_note_id');             // NULLs do not collide in Postgres
            $table->unique('supplier_vendor_credit_id');
            $table->index(['company_id', 'cancellation_date']);
        });

        DB::statement('ALTER TABLE umrah.ticket_cancellations ADD CONSTRAINT umrah_ticket_cancellations_supplier_rate_check CHECK ((supplier_returns_currency = base_currency AND supplier_returns_exchange_rate IS NULL) OR (supplier_returns_currency <> base_currency AND supplier_returns_exchange_rate > 0))');
        DB::statement('ALTER TABLE umrah.ticket_cancellations ADD CONSTRAINT umrah_ticket_cancellations_buyer_rate_check CHECK ((buyer_returns_currency = base_currency AND buyer_returns_exchange_rate IS NULL) OR (buyer_returns_currency <> base_currency AND buyer_returns_exchange_rate > 0))');
        DB::statement('ALTER TABLE umrah.ticket_cancellations ADD CONSTRAINT umrah_ticket_cancellations_amounts_check CHECK (supplier_returns_amount >= 0 AND supplier_returns_base >= 0 AND buyer_returns_amount >= 0 AND buyer_returns_base >= 0)');

        DB::statement('ALTER TABLE umrah.ticket_cancellations ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.ticket_cancellations FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY ticket_cancellations_company_isolation ON umrah.ticket_cancellations FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS ticket_cancellations_company_isolation ON umrah.ticket_cancellations');
        Schema::dropIfExists('umrah.ticket_cancellations');
    }
};
