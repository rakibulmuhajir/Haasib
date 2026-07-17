<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->uuid('amends_voucher_id')->nullable()->after('billing_voucher_id');
            $table->uuid('superseded_by_voucher_id')->nullable()->after('amends_voucher_id');
            $table->unsignedInteger('version_number')->default(1)->after('superseded_by_voucher_id');
            $table->timestamp('cancelled_at')->nullable()->after('created_by_user_id');
            $table->uuid('cancelled_by_user_id')->nullable()->after('cancelled_at');
            $table->text('cancellation_reason')->nullable()->after('cancelled_by_user_id');
            $table->timestamp('superseded_at')->nullable()->after('cancellation_reason');

            $table->foreign('amends_voucher_id')->references('id')->on('umrah.vouchers')->nullOnDelete();
            $table->foreign('superseded_by_voucher_id')->references('id')->on('umrah.vouchers')->nullOnDelete();
            $table->foreign('cancelled_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->index(['company_id', 'amends_voucher_id']);
            $table->index(['company_id', 'superseded_by_voucher_id']);
            $table->index(['company_id', 'status', 'superseded_at']);
        });

        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_status_check');
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('draft', 'approved', 'cancelled'))");
        DB::statement('ALTER TABLE umrah.voucher_passengers DROP CONSTRAINT IF EXISTS voucher_passengers_company_id_visa_group_id_passenger_id_unique');
        DB::statement('CREATE UNIQUE INDEX voucher_passengers_active_assignment_unique ON umrah.voucher_passengers (company_id, visa_group_id, passenger_id) WHERE deleted_at IS NULL');

        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->string('status', 20)->default('posted')->after('transaction_id');
            $table->timestamp('reversed_at')->nullable()->after('status');
            $table->uuid('reversed_by_user_id')->nullable()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by_user_id');
            $table->uuid('reversal_transaction_id')->nullable()->after('reversal_reason');

            $table->foreign('reversed_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('reversal_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete();
            $table->index(['company_id', 'status', 'payment_date']);
        });
        DB::statement("ALTER TABLE umrah.group_payments ADD CONSTRAINT group_payments_status_check CHECK (status IN ('posted', 'reversed'))");

        Schema::table('umrah.payment_allocations', function (Blueprint $table) {
            $table->timestamp('reversed_at')->nullable()->after('transaction_id');
            $table->uuid('reversed_by_user_id')->nullable()->after('reversed_at');
            $table->text('reversal_reason')->nullable()->after('reversed_by_user_id');
            $table->uuid('reversal_transaction_id')->nullable()->after('reversal_reason');

            $table->foreign('reversed_by_user_id')->references('id')->on('auth.users')->nullOnDelete();
            $table->foreign('reversal_transaction_id')->references('id')->on('acct.transactions')->nullOnDelete();
            $table->index(['company_id', 'group_payment_id', 'reversed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('umrah.payment_allocations', function (Blueprint $table) {
            $table->dropForeign(['reversed_by_user_id']);
            $table->dropForeign(['reversal_transaction_id']);
            $table->dropIndex(['company_id', 'group_payment_id', 'reversed_at']);
            $table->dropColumn(['reversed_at', 'reversed_by_user_id', 'reversal_reason', 'reversal_transaction_id']);
        });

        DB::statement('ALTER TABLE umrah.group_payments DROP CONSTRAINT IF EXISTS group_payments_status_check');
        Schema::table('umrah.group_payments', function (Blueprint $table) {
            $table->dropForeign(['reversed_by_user_id']);
            $table->dropForeign(['reversal_transaction_id']);
            $table->dropIndex(['company_id', 'status', 'payment_date']);
            $table->dropColumn(['status', 'reversed_at', 'reversed_by_user_id', 'reversal_reason', 'reversal_transaction_id']);
        });

        DB::statement('DROP INDEX IF EXISTS umrah.voucher_passengers_active_assignment_unique');
        DB::statement('ALTER TABLE umrah.voucher_passengers ADD CONSTRAINT voucher_passengers_company_id_visa_group_id_passenger_id_unique UNIQUE (company_id, visa_group_id, passenger_id)');
        DB::statement('ALTER TABLE umrah.vouchers DROP CONSTRAINT IF EXISTS vouchers_status_check');
        DB::statement("ALTER TABLE umrah.vouchers ADD CONSTRAINT vouchers_status_check CHECK (status IN ('draft', 'approved'))");

        Schema::table('umrah.vouchers', function (Blueprint $table) {
            $table->dropForeign(['amends_voucher_id']);
            $table->dropForeign(['superseded_by_voucher_id']);
            $table->dropForeign(['cancelled_by_user_id']);
            $table->dropIndex(['company_id', 'amends_voucher_id']);
            $table->dropIndex(['company_id', 'superseded_by_voucher_id']);
            $table->dropIndex(['company_id', 'status', 'superseded_at']);
            $table->dropColumn([
                'amends_voucher_id', 'superseded_by_voucher_id', 'version_number',
                'cancelled_at', 'cancelled_by_user_id', 'cancellation_reason', 'superseded_at',
            ]);
        });
    }
};
