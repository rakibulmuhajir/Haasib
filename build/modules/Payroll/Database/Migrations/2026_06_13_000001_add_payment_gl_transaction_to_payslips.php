<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pay.payslips', 'payment_gl_transaction_id')) {
            Schema::table('pay.payslips', function (Blueprint $table) {
                $table->uuid('payment_gl_transaction_id')->nullable()->after('gl_transaction_id');

                $table->foreign('payment_gl_transaction_id')
                    ->references('id')
                    ->on('acct.transactions')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pay.payslips', 'payment_gl_transaction_id')) {
            Schema::table('pay.payslips', function (Blueprint $table) {
                $table->dropForeign(['payment_gl_transaction_id']);
                $table->dropColumn('payment_gl_transaction_id');
            });
        }
    }
};
