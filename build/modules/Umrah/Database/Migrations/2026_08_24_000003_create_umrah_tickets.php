<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A ticket is dual-currency on purpose: the buyer's fare is sold in one
 * currency at one rate, the supplier's cost is billed in another
 * currency at another rate, and both convert to the same base currency
 * independently. Commission is never stored -- it is derived on the
 * model from gross_fare_base + taxes_base - supplier_cost_base, so a
 * corrected supplier cost cannot leave a stale commission behind.
 *
 * passenger_id points at umrah.passengers (the module's actual
 * passenger table -- there is no umrah.pilgrims) and is nullable with
 * nullOnDelete: passenger_name is a snapshot that survives the
 * passenger record going away.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.tickets', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('ticket_booking_id');
            $table->string('ticket_number', 32);                       // ours
            $table->string('airline_ticket_number', 32)->nullable();   // the airline's
            $table->uuid('passenger_id')->nullable();
            $table->string('passenger_name', 120);                     // snapshot, survives deletion
            $table->string('passport_number', 32)->nullable();
            $table->string('airline', 80)->nullable();
            $table->string('route', 120)->nullable();
            $table->date('travel_date')->nullable();

            // Sale side: the buyer's currency.
            $table->char('sale_currency', 3);
            $table->decimal('sale_exchange_rate', 18, 8)->nullable();
            $table->decimal('gross_fare', 18, 6);
            $table->decimal('taxes', 18, 6)->default(0);
            $table->decimal('discount', 18, 6)->default(0);
            $table->decimal('service_fee', 18, 6)->default(0);
            $table->decimal('gross_fare_base', 15, 2);
            $table->decimal('taxes_base', 15, 2)->default(0);
            $table->decimal('discount_base', 15, 2)->default(0);
            $table->decimal('service_fee_base', 15, 2)->default(0);

            // Supply side: the supplier's currency, a different rate.
            $table->char('supplier_currency', 3);
            $table->decimal('supplier_exchange_rate', 18, 8)->nullable();
            $table->decimal('supplier_cost', 18, 6);
            $table->decimal('supplier_cost_base', 15, 2);

            $table->char('base_currency', 3);
            $table->string('status', 20)->default('issued');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('ticket_booking_id')->references('id')->on('umrah.ticket_bookings')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('passenger_id')->references('id')->on('umrah.passengers')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('sale_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('supplier_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();
            $table->foreign('base_currency')->references('code')->on('public.currencies')->cascadeOnUpdate();

            $table->unique(['company_id', 'ticket_number']);
            $table->unique(['company_id', 'airline_ticket_number']);   // NULLs do not collide in Postgres
            $table->index(['company_id', 'ticket_booking_id']);
            $table->index(['company_id', 'travel_date']);
        });

        DB::statement('ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_sale_rate_check CHECK ((sale_currency = base_currency AND sale_exchange_rate IS NULL) OR (sale_currency <> base_currency AND sale_exchange_rate > 0))');
        DB::statement('ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_supplier_rate_check CHECK ((supplier_currency = base_currency AND supplier_exchange_rate IS NULL) OR (supplier_currency <> base_currency AND supplier_exchange_rate > 0))');
        DB::statement("ALTER TABLE umrah.tickets ADD CONSTRAINT umrah_tickets_status_check CHECK (status IN ('issued','cancelled'))");

        DB::statement('ALTER TABLE umrah.tickets ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE umrah.tickets FORCE ROW LEVEL SECURITY');
        DB::statement("CREATE POLICY tickets_company_isolation ON umrah.tickets FOR ALL USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid) WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid)");
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tickets_company_isolation ON umrah.tickets');
        Schema::dropIfExists('umrah.tickets');
    }
};
