<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bank.bank_transactions', function (Blueprint $table) {
            $table->foreign('reconciliation_id')
                  ->references('id')
                  ->on('bank.bank_reconciliations')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('bank.bank_transactions', function (Blueprint $table) {
            $table->dropForeign(['reconciliation_id']);
        });
    }
};