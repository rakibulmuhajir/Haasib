<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.hotel_vendors', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->string('vendor_number', 50);
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->string('city', 100)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_paid', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'vendor_number']);
            $table->index(['company_id', 'name']);
        });

        Schema::create('umrah.hotels', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('hotel_vendor_id');
            $table->string('name');
            $table->string('city', 100);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('hotel_vendor_id')->references('id')->on('umrah.hotel_vendors')->restrictOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'name', 'city']);
            $table->index(['company_id', 'is_active', 'city']);
        });

        Schema::create('umrah.hotel_room_rates', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('hotel_id');
            $table->string('room_type', 30);
            $table->decimal('retail_amount', 15, 2)->default(0);
            $table->decimal('cost_amount', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('hotel_id')->references('id')->on('umrah.hotels')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'hotel_id', 'room_type']);
            $table->index(['company_id', 'hotel_id', 'is_active']);
        });

        DB::statement("ALTER TABLE umrah.hotel_room_rates ADD CONSTRAINT hotel_room_rates_type_check CHECK (room_type IN ('single', 'double', 'triple', 'quad', 'quint'))");

        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->decimal('hotel_amount', 15, 2)->default(0)->after('transport_amount');
            $table->decimal('hotel_cost_amount', 15, 2)->default(0)->after('transport_cost_amount');
        });

        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->decimal('hotel_sale_amount', 15, 2)->default(0)->after('hotel_stays');
            $table->decimal('hotel_cost_amount', 15, 2)->default(0)->after('hotel_sale_amount');
            $table->uuid('hotel_sale_transaction_id')->nullable()->after('created_by_user_id');
            $table->uuid('hotel_cost_transaction_id')->nullable()->after('hotel_sale_transaction_id');
            $table->foreign('hotel_sale_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
            $table->foreign('hotel_cost_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete()->cascadeOnUpdate();
        });

        foreach (['hotel_vendors', 'hotels', 'hotel_room_rates'] as $table) {
            DB::statement("ALTER TABLE umrah.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE umrah.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON umrah.{$table}
                FOR ALL
                USING (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)
                WITH CHECK (company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true)
            ");
        }
    }

    public function down(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropForeign(['hotel_sale_transaction_id']);
            $table->dropForeign(['hotel_cost_transaction_id']);
            $table->dropColumn(['hotel_sale_amount', 'hotel_cost_amount', 'hotel_sale_transaction_id', 'hotel_cost_transaction_id']);
        });
        Schema::table('umrah.visa_groups', function (Blueprint $table) {
            $table->dropColumn(['hotel_amount', 'hotel_cost_amount']);
        });
        foreach (['hotel_room_rates', 'hotels', 'hotel_vendors'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
            Schema::dropIfExists("umrah.{$table}");
        }
    }
};
