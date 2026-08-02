<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay.payslips', function (Blueprint $table) {
            $table->timestamp('voided_at')->nullable();
            $table->foreignUuid('voided_by_user_id')->nullable()
                ->references('id')->on('auth.users')->nullOnDelete()->cascadeOnUpdate();
            $table->string('void_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pay.payslips', function (Blueprint $table) {
            $table->dropForeign(['voided_by_user_id']);
            $table->dropColumn(['voided_at', 'voided_by_user_id', 'void_reason']);
        });
    }
};
