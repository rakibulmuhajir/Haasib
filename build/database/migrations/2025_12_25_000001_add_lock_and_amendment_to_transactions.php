<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acct.transactions', function (Blueprint $table) {
            // Lock mechanism
            $table->boolean('is_locked')->default(false)->after('void_reason');
            $table->timestamp('locked_at')->nullable()->after('is_locked');
            $table->uuid('locked_by_user_id')->nullable()->after('locked_at');
            $table->string('lock_reason', 50)->nullable()->after('locked_by_user_id');

            // Amendment tracking (supplements existing reversal_of_id/reversed_by_id)
            $table->uuid('corrects_transaction_id')->nullable()->after('reversed_by_id');
            $table->string('amendment_reason', 500)->nullable()->after('corrects_transaction_id');
            $table->timestamp('amended_at')->nullable()->after('amendment_reason');
            $table->uuid('amended_by_user_id')->nullable()->after('amended_at');

            // Foreign key constraints
            $table->foreign('locked_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->nullOnDelete();

            $table->foreign('amended_by_user_id')
                ->references('id')
                ->on('auth.users')
                ->nullOnDelete();

            $table->foreign('corrects_transaction_id')
                ->references('id')
                ->on('acct.transactions')
                ->nullOnDelete();

            // Indexes
            $table->index('is_locked');
            $table->index('corrects_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('acct.transactions', function (Blueprint $table) {
            // Drop foreign keys first
            $table->dropForeign(['locked_by_user_id']);
            $table->dropForeign(['amended_by_user_id']);
            $table->dropForeign(['corrects_transaction_id']);

            $table->dropIndex(['is_locked']);
            $table->dropIndex(['corrects_transaction_id']);

            $table->dropColumn([
                'is_locked',
                'locked_at',
                'locked_by_user_id',
                'lock_reason',
                'corrects_transaction_id',
                'amendment_reason',
                'amended_at',
                'amended_by_user_id',
            ]);
        });
    }
};
