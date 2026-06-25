<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umrah.vouchers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('visa_group_id');
            $table->uuid('agent_id');
            $table->string('voucher_number', 50);
            $table->string('title');
            $table->string('status', 30)->default('draft');
            $table->string('onward_airline', 150);
            $table->string('onward_flight_number', 80)->nullable();
            $table->timestamp('onward_departure_at');
            $table->timestamp('onward_arrival_at');
            $table->string('return_airline', 150);
            $table->string('return_flight_number', 80)->nullable();
            $table->timestamp('return_departure_at');
            $table->timestamp('return_arrival_at');
            $table->jsonb('hotel_stays')->default(DB::raw("'[]'::jsonb"));
            $table->text('notes')->nullable();
            $table->uuid('created_by_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('agent_id')->references('id')->on('umrah.agents')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('created_by_user_id')->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'voucher_number']);
            $table->index(['company_id', 'visa_group_id']);
            $table->index(['company_id', 'agent_id']);
            $table->index(['company_id', 'status']);
            $table->index(['company_id', 'onward_departure_at']);
        });

        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_status_check
            CHECK (status IN ('draft', 'issued', 'cancelled'))");

        Schema::create('umrah.voucher_passengers', function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('public.gen_random_uuid()'));
            $table->uuid('company_id');
            $table->uuid('voucher_id');
            $table->uuid('visa_group_id');
            $table->uuid('passenger_id');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('company_id')->references('id')->on('auth.companies')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('voucher_id')->references('id')->on('umrah.vouchers')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('visa_group_id')->references('id')->on('umrah.visa_groups')->cascadeOnDelete()->cascadeOnUpdate();
            $table->foreign('passenger_id')->references('id')->on('umrah.passengers')->cascadeOnDelete()->cascadeOnUpdate();
            $table->unique(['company_id', 'visa_group_id', 'passenger_id']);
            $table->index(['company_id', 'voucher_id']);
        });

        foreach (['vouchers', 'voucher_passengers'] as $table) {
            DB::statement("ALTER TABLE umrah.{$table} ENABLE ROW LEVEL SECURITY");
            DB::statement("ALTER TABLE umrah.{$table} FORCE ROW LEVEL SECURITY");
            DB::statement("
                CREATE POLICY {$table}_company_isolation ON umrah.{$table}
                FOR ALL
                USING (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
                WITH CHECK (
                    company_id = NULLIF(current_setting('app.current_company_id', true), '')::uuid
                    OR COALESCE(NULLIF(current_setting('app.is_super_admin', true), '')::boolean, false) = true
                )
            ");
        }
    }

    public function down(): void
    {
        foreach (['voucher_passengers', 'vouchers'] as $table) {
            DB::statement("DROP POLICY IF EXISTS {$table}_company_isolation ON umrah.{$table}");
        }

        Schema::dropIfExists('umrah.voucher_passengers');
        Schema::dropIfExists('umrah.vouchers');
    }
};
